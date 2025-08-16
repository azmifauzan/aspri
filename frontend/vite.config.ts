import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [
    react({
      // Ensure the modern (automatic) JSX runtime is used for faster
      // runtime and smaller bundles. This aligns with TypeScript's
      // "jsx": "react-jsx" compiler option in this project.
      jsxRuntime: 'automatic',
    }),
  ],
});