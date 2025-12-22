# Aspri Frontend

[![React](https://img.shields.io/badge/React-19-blue.svg)](https://reactjs.org/)
[![Vite](https://img.shields.io/badge/Vite-4-blue.svg)](https://vitejs.dev/)
[![TypeScript](https://img.shields.io/badge/TypeScript-5-blue.svg)](https://www.typescriptlang.org/)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind%20CSS-3-blue.svg)](https://tailwindcss.com/)

This is the frontend for the Aspri personal assistant application. It provides a modern and responsive user interface for interacting with the Aspri backend.

## Key Features

- **Authentication**: Secure login and registration using Google OAuth.
- **Dashboard**: A user-friendly dashboard to access all features.
- **Document Management**: Upload, view, and manage your documents.
- **Chat Interface**: Interact with the AI assistant through a real-time chat interface.
- **Finance Management**: Track income and expenses, manage categories, and view financial summaries.
- **Internationalization**: Support for multiple languages using i18next.
- **Responsive Design**: A clean and responsive UI built with Tailwind CSS.

## Technology Stack

- **React**: A JavaScript library for building user interfaces.
- **Vite**: Next-generation frontend tooling for a fast development experience.
- **TypeScript**: A typed superset of JavaScript that compiles to plain JavaScript.
- **Tailwind CSS**: A utility-first CSS framework for rapid UI development.
- **React Router**: For client-side routing.
- **@react-oauth/google**: To handle Google OAuth integration.
- **i18next**: An internationalization-framework written in and for JavaScript.
- **axios**: A promise-based HTTP client for making API requests.
- **Lucide React**: A library of simply beautiful open-source icons.

## Project Structure

```
aspri/frontend/
├── src/
│   ├── components/          # Reusable React components
│   ├── pages/              # Page components
│   ├── hooks/              # Custom React hooks
│   ├── services/           # API service functions
│   ├── types/              # TypeScript type definitions
│   ├── utils/              # Utility functions
│   ├── i18n/               # Internationalization files
│   └── main.tsx            # Application entry point
├── public/
│   └── ...                 # Static assets
├── package.json            # Dependencies and scripts
├── Dockerfile              # Container configuration
├── nginx.conf              # Nginx configuration for production
├── .env.example            # Environment variables template
└── README.md               # This file
```

## Getting Started

### Option 1: Docker Compose (Recommended)

The easiest way to run the frontend along with the entire application stack:

1. **Navigate to project root:**
   ```bash
   cd aspri
   ```

2. **Configure environment variables:**
   ```bash
   # Frontend configuration
   cd frontend
   cp .env.example .env
   # Edit .env with your Google Client ID
   
   # Backend configuration (if not done already)
   cd ../backend
   cp .env.template .env
   # Edit .env with your configuration
   
   cd ..
   ```

3. **Start all services:**
   ```bash
   docker-compose up --build
   ```

4. **Access the application:**
   - Frontend: http://localhost:3000
   - Backend API: http://localhost:8888

### Option 2: Local Development

To run just the frontend for development:

1. **Navigate to the frontend directory:**
   ```bash
   cd frontend
   ```

2. **Install dependencies:**
   ```bash
   npm install
   ```

3. **Set up environment variables:**
   ```bash
   cp .env.example .env
   ```
   Add your Google Client ID to the `.env` file:
   ```env
   VITE_GOOGLE_CLIENT_ID=your-google-client-id.apps.googleusercontent.com
   VITE_API_BASE_URL=http://localhost:8888
   ```

4. **Start the development server:**
   ```bash
   npm run dev
   ```
   The application will be available at `http://localhost:5173`.

## Available Scripts

- `npm run dev` - Start development server
- `npm run build` - Build for production
- `npm run preview` - Preview production build locally
- `npm run lint` - Run ESLint for code quality
```

## Docker Support

The frontend includes Docker support for containerized deployment:

- **Dockerfile**: Multi-stage build with Node.js and Nginx
- **nginx.conf**: Production-ready Nginx configuration with API proxy
- **docker-compose.yml**: Full-stack orchestration (located at project root)

## Production Deployment

The application is built as a static site and served through Nginx:

1. Frontend assets are built using Vite
2. Static files are served by Nginx
3. API requests are proxied to the backend service
4. React Router is supported with proper fallback configuration

## API Integration

The frontend communicates with the backend through RESTful APIs:

- Authentication endpoints for Google OAuth
- Document management for file operations
- Chat endpoints for AI interactions
- Configuration endpoints for app settings

All API calls are made through axios with proper error handling and authentication tokens.

## Contributing

Contributions are what make the open-source community such an amazing place to learn, inspire, and create. Any contributions you make are **greatly appreciated**.

1. Fork the Project
2. Create your Feature Branch (`git checkout -b feature/AmazingFeature`)
3. Commit your Changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the Branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## License

Distributed under the MIT License. See `LICENSE` for more information.