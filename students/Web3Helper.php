<?php

/**
 * Web3Helper - A utility class for interacting with Ethereum blockchain
 * 
 * This class provides methods to interact with the Ethereum blockchain
 * for NFT minting operations.
 */
class Web3Helper
{
    private $rpcUrl;
    private $privateKey;
    private $contractAddress;
    private $contractAbi;

    /**
     * Constructor
     * 
     * @param string $rpcUrl Ethereum RPC URL
     * @param string $privateKey Private key for signing transactions
     * @param string $contractAddress NFT contract address
     */
    public function __construct($rpcUrl, $privateKey, $contractAddress)
    {
        $this->rpcUrl = $rpcUrl;
        $this->privateKey = $privateKey;
        $this->contractAddress = $contractAddress;

        // Load contract ABI from file
        $abiFile = __DIR__ . '/NFTContract.json';
        if (file_exists($abiFile)) {
            $abiData = json_decode(file_get_contents($abiFile), true);
            $this->contractAbi = json_encode($abiData['abi']);
        } else {
            // Fallback to just the mint function if file not found
            $this->contractAbi = json_encode([
                [
                    "inputs" => [
                        ["name" => "tokenURI", "type" => "string"]
                    ],
                    "name" => "mint",
                    "outputs" => [
                        ["name" => "", "type" => "uint256"]
                    ],
                    "stateMutability" => "nonpayable",
                    "type" => "function"
                ]
            ]);
        }
    }

    /**
     * Get the address from the private key
     * 
     * @return string Ethereum address
     */
    public function getAddress()
    {
        // This is a simplified method to derive an address
        // In a real implementation, you'd use proper cryptographic libraries
        // like keccak256 and secp256k1 to derive the address from the private key
        return '0x' . substr(hash('sha256', $this->privateKey), 0, 40);
    }

    /**
     * Encode ABI for mint function
     * 
     * @param string $tokenURI IPFS URI for the token metadata
     * @return string Encoded ABI data
     */
    private function encodeMintFunction($tokenURI)
    {
        // Function signature hash for mint(string)
        $functionSignature = '0x40c10f19';

        // This is a simplified ABI encoding - in production use proper libraries
        // Encode string parameter (simplified)
        $encodedURI = bin2hex($tokenURI);
        $uriLength = dechex(strlen($tokenURI));
        $padding = str_repeat('0', 64 - strlen($uriLength));

        return $functionSignature . $padding . $uriLength . $encodedURI;
    }

    /**
     * Mint an NFT with the given token URI
     * 
     * @param string $tokenURI IPFS URI for the token metadata
     * @return array Transaction details
     */
    public function mintNFT($tokenURI)
    {
        $address = $this->getAddress();

        // Debug info
        error_log("Web3Helper: Preparing to mint NFT with token URI: " . $tokenURI);
        error_log("Web3Helper: Using contract address: " . $this->contractAddress);
        error_log("Web3Helper: Using RPC URL: " . $this->rpcUrl);

        try {
            // Initialize cURL
            $ch = curl_init($this->rpcUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POST, true);
            // SSL options for development (disable SSL verification)
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30 second timeout

            // Get nonce
            $nonceData = json_encode([
                'jsonrpc' => '2.0',
                'method' => 'eth_getTransactionCount',
                'params' => [$address, 'latest'],
                'id' => 1
            ]);

            error_log("Web3Helper: Requesting nonce for address: " . $address);

            curl_setopt($ch, CURLOPT_POSTFIELDS, $nonceData);
            $nonceResponse = curl_exec($ch);

            if (curl_errno($ch)) {
                $curlError = curl_error($ch);
                error_log("Web3Helper: cURL Error in nonce request: " . $curlError);
                throw new Exception('cURL Error: ' . $curlError);
            }

            // Check if response is empty
            if (empty($nonceResponse)) {
                error_log("Web3Helper: Empty response from RPC endpoint");
                throw new Exception('Empty response from RPC endpoint. Please check your SEPOLIA_RPC_URL.');
            }

            error_log("Web3Helper: Nonce response: " . $nonceResponse);

            $nonceResult = json_decode($nonceResponse, true);

            // Check if json_decode failed
            if ($nonceResult === null) {
                error_log("Web3Helper: Failed to decode JSON response: " . $nonceResponse);
                throw new Exception('Invalid JSON response from RPC endpoint');
            }

            if (isset($nonceResult['error'])) {
                error_log("Web3Helper: JSON-RPC Error in nonce request: " . json_encode($nonceResult['error']));
                throw new Exception('JSON-RPC Error: ' . $nonceResult['error']['message']);
            }

            // Check if result exists
            if (!isset($nonceResult['result'])) {
                error_log("Web3Helper: No result field in response: " . json_encode($nonceResult));
                throw new Exception('Invalid response format from RPC endpoint');
            }

            $nonce = $nonceResult['result'];
            error_log("Web3Helper: Got nonce: " . $nonce);

            // Get gas price
            $gasPriceData = json_encode([
                'jsonrpc' => '2.0',
                'method' => 'eth_gasPrice',
                'params' => [],
                'id' => 2
            ]);

            error_log("Web3Helper: Requesting gas price");

            curl_setopt($ch, CURLOPT_POSTFIELDS, $gasPriceData);
            $gasPriceResponse = curl_exec($ch);

            // Validate gas price response
            if (empty($gasPriceResponse)) {
                error_log("Web3Helper: Empty gas price response, using default");
                $gasPrice = '0x3b9aca00'; // Default 1 gwei
            } else {
                $gasPriceResult = json_decode($gasPriceResponse, true);
                if ($gasPriceResult === null || !isset($gasPriceResult['result'])) {
                    error_log("Web3Helper: Invalid gas price response, using default");
                    $gasPrice = '0x3b9aca00'; // Default 1 gwei
                } else {
                    $gasPrice = $gasPriceResult['result'];
                }
            }

            error_log("Web3Helper: Got gas price: " . $gasPrice);

            // Create raw transaction
            $txData = [
                'nonce' => $nonce,
                'gasPrice' => $gasPrice,
                'gasLimit' => '0x100000',
                'to' => $this->contractAddress,
                'value' => '0x0',
                'data' => $this->encodeMintFunction($tokenURI),
                'chainId' => 11155111 // Sepolia chain ID
            ];

            error_log("Web3Helper: Prepared transaction data: " . json_encode($txData));

            // For demonstration, we'll use the ethers.js approach client-side for simplicity
            // Returning transaction data for client-side processing
            return [
                'success' => true,
                'message' => 'Transaction data prepared',
                'data' => [
                    'tx_data' => $txData,
                    'rpc_url' => $this->rpcUrl,
                    'contract_address' => $this->contractAddress,
                    'private_key' => $this->privateKey // Note: In production, never expose this
                ]
            ];
        } catch (Exception $e) {
            error_log("Web3Helper: Error in mintNFT: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
