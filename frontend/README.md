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

## Getting Started

To get a local copy up and running, follow these simple steps.

### Prerequisites

- Node.js (v18+ recommended)
- npm

### Installation

1.  Navigate to the `frontend` directory:
    ```sh
    cd frontend
    ```
2.  Install NPM packages:
    ```sh
    npm install
    ```
3.  Set up your environment variables by creating a `.env` file. You can copy the example file:
    ```sh
    cp .env.example .env
    ```
    Then, add your Google Client ID to the `.env` file:
    ```env
    VITE_GOOGLE_CLIENT_ID=your-google-client-id.apps.googleusercontent.com
    ```
4.  Start the development server:
    ```sh
    npm run dev
    ```
    The application will be available at `http://localhost:5173`.

## Contributing

Contributions are what make the open-source community such an amazing place to learn, inspire, and create. Any contributions you make are **greatly appreciated**.

1.  Fork the Project
2.  Create your Feature Branch (`git checkout -b feature/AmazingFeature`)
3.  Commit your Changes (`git commit -m 'Add some AmazingFeature'`)
4.  Push to the Branch (`git push origin feature/AmazingFeature`)
5.  Open a Pull Request

## License

Distributed under the MIT License. See `LICENSE` for more information.
