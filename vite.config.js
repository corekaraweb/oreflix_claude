import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
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
