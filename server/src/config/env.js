import dotenv from 'dotenv';

dotenv.config();

function numberFromEnv(value, fallback) {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
}

export const env = {
  NODE_ENV: process.env.NODE_ENV || 'development',
  PORT: numberFromEnv(process.env.PORT, 5000),
  CLIENT_ORIGIN: process.env.CLIENT_ORIGIN || 'http://localhost:5173',
  MONGODB_URI:
    process.env.MONGODB_URI || 'mongodb://127.0.0.1:27017/examflow',
  SESSION_SECRET: process.env.SESSION_SECRET || 'replace_me',
  JWT_ACCESS_SECRET: process.env.JWT_ACCESS_SECRET || 'replace_me',
  JWT_REFRESH_SECRET: process.env.JWT_REFRESH_SECRET || 'replace_me',
  GROQ_API_KEY: process.env.GROQ_API_KEY || '',
  GROQ_MODEL: process.env.GROQ_MODEL || 'llama-3.3-70b-versatile',
  GROQ_API_URL:
    process.env.GROQ_API_URL ||
    'https://api.groq.com/openai/v1/chat/completions',
  TESSERACT_PATH: process.env.TESSERACT_PATH || 'tesseract',
  SMTP_SERVER: process.env.SMTP_SERVER || '',
  SMTP_PORT: numberFromEnv(process.env.SMTP_PORT, 587),
  SMTP_SECURE: process.env.SMTP_SECURE || 'tls',
  SMTP_USERNAME: process.env.SMTP_USERNAME || '',
  SMTP_PASSWORD: process.env.SMTP_PASSWORD || '',
  FROM_EMAIL: process.env.FROM_EMAIL || '',
  FROM_NAME: process.env.FROM_NAME || 'ExamFlow',
  PINATA_JWT: process.env.PINATA_JWT || '',
  PINATA_API_KEY: process.env.PINATA_API_KEY || '',
  PINATA_SECRET_KEY: process.env.PINATA_SECRET_KEY || '',
  SEPOLIA_RPC_URL: process.env.SEPOLIA_RPC_URL || '',
  WALLET_PRIVATE_KEY: process.env.WALLET_PRIVATE_KEY || '',
  NFT_CONTRACT_ADDRESS: process.env.NFT_CONTRACT_ADDRESS || '',
  ETHERSCAN_API_KEY: process.env.ETHERSCAN_API_KEY || ''
};
