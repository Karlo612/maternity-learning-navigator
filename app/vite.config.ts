import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react-swc';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({ input: ['resources/css/app.css', 'resources/js/app.tsx'], refresh: true }),
        react(),
        VitePWA({
            registerType: 'autoUpdate',
            scope: '/',
            manifest: {
                name: 'Maternity Learning Navigator',
                short_name: 'Learning Navigator',
                description: 'Review-gated maternity learning resource routing.',
                theme_color: '#432f44',
                background_color: '#f7f1e8',
                display: 'standalone',
                start_url: '/',
                scope: '/',
                icons: [
                    { src: '/icon-192.png', sizes: '192x192', type: 'image/png' },
                    { src: '/icon-512.png', sizes: '512x512', type: 'image/png' },
                    { src: '/icon-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
                ],
            },
            workbox: {
                navigateFallback: '/',
                runtimeCaching: [{
                    urlPattern: ({ url }) => url.pathname === '/api/v1/resources',
                    handler: 'NetworkFirst',
                    options: { cacheName: 'reviewed-resource-metadata', networkTimeoutSeconds: 3 },
                }],
            },
        }),
    ],
});
