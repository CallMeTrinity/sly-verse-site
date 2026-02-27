import { defineConfig, loadEnv } from 'vite'
import tailwindcss from '@tailwindcss/vite'
import ViteRestart from 'vite-plugin-restart'

// https://vitejs.dev/config/
export default defineConfig(({ command, mode }) => {
	const env = loadEnv(mode, process.cwd(), '')
	const siteUrl = env.PRIMARY_SITE_URL || 'http://localhost'
	const parsedUrl = new URL(siteUrl)
	const devOrigin = `${parsedUrl.protocol}//${parsedUrl.hostname}:3000`

	return {
		base: command === 'serve' ? '' : '/dist/',

		build: {
			emptyOutDir: true,
			manifest: true,
			outDir: 'web/dist/',
			rollupOptions: {
				input: {
					app: 'src/js/app.js',
				},
			},
		},

		plugins: [
			// The restart plugin allows Vite to refresh pages when Twig files change
			ViteRestart({
				reload: ['templates/**/*'],
			}),
			tailwindcss(),
		],

		// Anything in publicDir will be copied into web/dist during `npm run build`
		publicDir: './src/public',

		// https://nystudio107.com/docs/vite/#specifying-the-dev-server-port
		server: {
			// Allow cross-origin requests -- https://github.com/vitejs/vite/security/advisories/GHSA-vg6x-rcgg-rjx6
			allowedHosts: true,
			cors: {
				origin:
					/https?:\/\/([A-Za-z0-9\-\.]+)?(localhost|\.local|\.test|\.site)(?::\d+)?$/,
			},
			fs: {
				strict: false,
			},
			headers: {
				'Access-Control-Allow-Private-Network': 'true',
			},
			host: '0.0.0.0',
			origin: devOrigin,
			port: 3000,
			strictPort: true,
		},
	}
})
