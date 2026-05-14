# ASPRI AI Agent Instructions

Welcome to the ASPRI (Asisten Pribadi Berbasis AI) codebase. This document provides critical context, architectural rules, and workflow requirements for AI agents.

## 1. Project Overview
ASPRI is an AI-powered personal assistant for managing schedules and finances. It features a "Chat-First" experience, where users interact primarily through natural language on the Web or Telegram.

**Core Tech Stack:**
- **Backend:** Laravel 12, PHP 8.4, PostgreSQL.
- **Frontend:** Vue 3.5 (Composition API), Inertia.js v2, Tailwind CSS 4, TypeScript.
- **AI Providers:** Multi-provider support (Gemini, OpenAI, Claude).
- **Tooling:** Laravel Wayfinder (type-safe routes), Reka UI (components), Laravel Pint (formatting).

---

## 2. Critical Guidelines (ALWAYS FOLLOW)

### 2.1. Persona & Language
Every AI response MUST respect the user's profile settings:
- **`call_preference`**: How to address the user (e.g., "Kak", "Bapak").
- **`aspri_name`**: The assistant's name (e.g., "Jarvis").
- **`aspri_persona`**: The personality style (e.g., "pria", "wanita", "kucing").
- **Language Detection**: ASPRI automatically detects the user's language (ID/EN) and responds accordingly. Maintain the detected language.

### 2.2. Safety First (Confirmation Flow)
Mutations (Create, Update, Delete) for Finance and Schedule MUST go through a **Confirmation Flow**:
1. AI identifies the intent.
2. AI creates a `PendingAction`.
3. AI asks for confirmation (e.g., "Apakah Kakak yakin ingin menghapus transaksi ini?").
4. Action is only executed after user confirms ("Ya" or similar).
*Note: Notes module operations are currently non-destructive (except delete) and may execute directly.*

### 2.3. Conversation Memory
ASPRI implements a cross-session memory system using `ConversationMemoryService`.
- **Memory Budget**: Controlled by `ai_context_length` in Admin settings.
- **Memory Context**: Injected into the system prompt for all providers.
- **Memory Extraction**: Handled asynchronously via `ExtractConversationMemories` job.

---

## 3. Architecture & Patterns

### 3.1. Service Layer (`app/Services/`)
Keep controllers thin. Business logic belongs in services:
- `Ai/`: Abstractions for AI providers and the `ChatOrchestrator`.
- `Admin/`: System settings and monitoring.
- `Plugin/`: Lifecycle management for the extensible plugin system.
- `Subscription/`: Trial and premium tier management.
- `Telegram/`: Bot integration logic.

### 3.2. Frontend Patterns (`resources/js/`)
- **Pages**: Located in `resources/js/pages/`.
- **Components**: UI components use **Reka UI** and are found in `resources/js/components/ui/`.
- **Routing**: Use **Wayfinder** for type-safe routing.
    - Import actions: `import { store } from '@/actions/App/Http/Controllers/ChatController'`
    - Use in templates: `<Form v-bind="store.form()">`
- **Styling**: Tailwind CSS 4 (CSS-first configuration in `app.css`).

---

## 4. Workflow & Commands

### 4.1. Creating New Features
- Use `php artisan make:*` commands (with `--no-interaction`).
- Create **Form Request** classes for all validation.
- Update **Model Factories** and **Seeders**.
- Add **Feature Tests** (PHPUnit) for all new endpoints.

### 4.2. Formatting & Testing
- **Formatting**: Run `vendor/bin/pint --dirty` before submitting changes.
- **Testing**: Run `php artisan test --compact --filter=ClassName` for specific tests.
- **Type Checking**: Ensure `npm run type-check` passes.

### 4.3. Useful Commands
- `php artisan wayfinder:generate`: Re-sync TypeScript routes.
- `php artisan list-artisan-commands`: Check available custom commands.
- `vendor/bin/pint`: Fix PHP styling.

---

## 5. Documentation Reference
Refer to these files in the `docs/` folder for deeper dives:
- `ARCHITECTURE.md`: High-level system design and data flows.
- `CURRENT_STATUS.md`: Live features and known limitations.
- `DATABASE.md`: Schema details and entity relationships.
- `PLAN.md`: Development roadmap.
- `laravel-boost.md`: Laravel-specific best practices and conventions.

---

**Remember**: ASPRI is designed to feel premium and intelligent. Avoid generic UI/UX. Prioritize visual excellence and smooth micro-interactions.
