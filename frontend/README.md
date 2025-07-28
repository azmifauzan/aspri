# Aspri Frontend

[![React](https://img.shields.io/badge/React-19-blue.svg)](https://reactjs.org/)
[![Vite](https://img.shields.io/badge/Vite-4-blue.svg)](https://vitejs.dev/)
[![TypeScript](https://img.shields.io/badge/TypeScript-5-blue.svg)](https://www.typescriptlang.org/)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind%20CSS-3-blue.svg)](https://tailwindcss.com/)

Aspri is a personal assistant application that helps you manage your schedule, finances, and documents.

## Key Features

- **Smart Scheduling:** Add, edit, and sync events with Google Calendar.
- **Smart Email:** Read and Send email with Google Mail.
- **Smart Contact:** Add, edit, and sync contact with Google Contact.
- **Financial Insights:** Track income and expenses, with AI-powered monthly summaries.
- **Intelligent Document Analysis:** Upload PDFs and ask questions to get instant answers from an LLM.
- **Chat-based Interface:** Interact with the assistant through a simple and intuitive chat interface.

## Technology Stack

- [React](https://reactjs.org/)
- [Vite](https://vitejs.dev/)
- [TypeScript](https://www.typescriptlang.org/)
- [Tailwind CSS](https://tailwindcss.com/)
- [i18next](https://www.i18next.com/)
- [Lucide React](https://lucide.dev/guide/react)

## Getting Started

To get a local copy up and running, follow these simple steps.

### Prerequisites

- [Node.js](https://nodejs.org/en/)
- [npm](https://www.npmjs.com/)

### Installation

1.  Clone the repo
    ```sh
    git clone https://github.com/your_username_/Project-Name.git
    ```
2.  Install NPM packages
    ```sh
    npm install
    ```
3.  Start the development server
    ```sh
    npm run dev
    ```

## Project Structure

```
D:/dev/aspri/frontend
├── .gitignore
├── eslint.config.js
├── index.html
├── package-lock.json
├── package.json
├── postcss.config.js
├── README.md
├── tailwind.config.js
├── tsconfig.app.json
├── tsconfig.json
├── tsconfig.node.json
├── vite.config.ts
├── node_modules/
├── public/
│   └── vite.svg
└── src/
    ├── App.css
    ├── App.tsx
    ├── i18n.ts
    ├── index.css
    ├── main.tsx
    ├── vite-env.d.ts
    ├── assets/
    │   └── react.svg
    ├── components/
    │   ├── Badge.tsx
    │   ├── ChatBubble.tsx
    │   ├── ChatPreview.tsx
    │   ├── FeatureCard.tsx
    │   ├── FeatureSection.tsx
    │   ├── Footer.tsx
    │   ├── Hero.tsx
    │   ├── LangToggle.tsx
    │   ├── Navbar.tsx
    │   ├── ProblemSolution.tsx
    │   ├── TechAdvantages.tsx
    │   ├── ThemeToggle.tsx
    │   └── Workflow.tsx
    ├── hooks/
    │   └── useDarkMode.ts
    ├── locales/
    │   ├── en/
    │   │   └── common.json
    │   └── id/
    │       └── common.json
    └── pages/
        └── LandingPage.tsx
```

## Contributing

Contributions are what make the open-source community such an amazing place to learn, inspire, and create. Any contributions you make are **greatly appreciated**.

1.  Fork the Project
2.  Create your Feature Branch (`git checkout -b feature/AmazingFeature`)
3.  Commit your Changes (`git commit -m 'Add some AmazingFeature'`)
4.  Push to the Branch (`git push origin feature/AmazingFeature`)
5.  Open a Pull Request

## License

Distributed under the MIT License. See `LICENSE` for more information.
