import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  build: {
    // Transpile further than Vite's default "evergreen browsers" target so
    // the bundle still parses on older iOS/Safari — a syntax error there
    // fails silently with no console access on-device, so compatibility
    // matters more than output size here.
    target: ['es2018', 'safari13'],
  },
  server: {
    // In local dev, PHP isn't executed by Vite. Run `npm run dev:php`
    // alongside `npm run dev` (see README) and requests to /api and /admin
    // are forwarded to it.
    proxy: {
      '/api': 'http://localhost:8080',
      '/admin': 'http://localhost:8080',
    },
  },
})
