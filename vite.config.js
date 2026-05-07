import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.jsx',
            refresh: true,
        }),
        react(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'), // 確保這行正確指向 js 資料夾
        },
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true, // 確保埠號固定，不會跳到 5174
        cors: true,       // 關鍵：允許跨來源請求
        origin: 'http://192.168.0.10:5173', // 關鍵：明確定義來源
        hmr: {
            host: '192.168.0.10',
        },
    },
    // ... 其他配置
});
