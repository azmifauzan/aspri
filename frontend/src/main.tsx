import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App'
import "./i18n";

// Dev-only: filter a noisy React dev warning about the "outdated JSX transform"
// coming from some dependencies (for example, react-big-calendar). This keeps
// the browser console focused on real issues during development. We do not
// silence other warnings/errors and this code runs only in DEV.
if (import.meta.env.DEV) {
  const _warn = console.warn.bind(console);
  console.warn = (...args: any[]) => {
    try {
      const first = args[0];
      if (typeof first === 'string' && first.includes('outdated JSX transform')) {
        return;
      }
    } catch (e) {
      // fall through to original warn
    }
    _warn(...args);
  };
}

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)
