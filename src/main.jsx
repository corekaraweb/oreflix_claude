import './domPatch.js'
import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App.jsx'
import ErrorBoundary from './components/ErrorBoundary.jsx'

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <ErrorBoundary>
      <App />
    </ErrorBoundary>
  </StrictMode>,
)

// Lets the fallback script in index.html know React mounted successfully,
// so it won't overwrite a working page for errors unrelated to the initial
// mount (those are instead caught by <ErrorBoundary> above).
document.getElementById('root').dataset.mounted = '1'
