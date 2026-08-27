import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    resolve: {
        alias: {
            '@': '/Users/rada/Documents/sul proyek/app/resources/js',
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
                bunny('Inter', {
                    weights: [400, 500, 600, 700],
                }),
            ],
        }),
        react(),
        tailwindcss(),
        VitePWA({
            registerType: 'autoUpdate',
            devOptions: { enabled: false }, // disable PWA di dev agar tidak ganggu HMR preamble
            includeAssets: ['favicon.ico', 'robots.txt'],
            manifest: {
                name: 'BBWS Pompengan Jeneberang - Karyawan',
                short_name: 'BBWS PJ',
                description: 'PWA Karyawan BBWS Pompengan Jeneberang - Pusat Makassar',
                theme_color: '#1E3A8A',
                background_color: '#ffffff',
                display: 'standalone',
                start_url: '/karyawan',
                icons: [
                    { src: '/pwa-192x192.png', sizes: '192x192', type: 'image/png' },
                    { src: '/pwa-512x512.png', sizes: '512x512', type: 'image/png', purpose: 'any maskable' },
                ],
            },
            workbox: {
                globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2}'],
                runtimeCaching: [
                    {
                        urlPattern: /^https:\/\/.*\.s3\.amazonaws\.com\/.*/i,
                        handler: 'CacheFirst',
                        options: { cacheName: 's3-media', expiration: { maxEntries: 100, maxAgeSeconds: 60 * 60 * 24 * 7 } },
                    },
                    {
                        urlPattern: /\/api\/karyawan\/.*/,
                        handler: 'NetworkFirst',
                        options: { cacheName: 'karyawan-api', networkTimeoutSeconds: 5 },
                    },
                ],
            },
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
