# ASPRI Development Phases

## Phase Overview

Total durasi: 14 minggu

| Phase | Name | Duration | Focus |
|-------|------|----------|-------|
| 1 | Foundation | 2 minggu | Setup, Auth, Layout |
| 2 | Finance | 2 minggu | Finance CRUD, Charts |
| 3 | Schedule | 2 minggu | Calendar, Events, Reminders |
| 4 | Notes | 2 minggu | Block Editor, Tags, Search |
| 5 | Chat & AI | 3 minggu | Chat UI, AI Integration |
| 6 | Telegram | 2 minggu | Bot Integration |
| 7 | Polish | 1 minggu | Testing, Optimization |

---

## Phase 1: Foundation

**Duration**: Week 1-2

### Goals
- Working Laravel 12 application
- User authentication (register/login)
- Base layout with sidebar
- Dashboard placeholder

### Tasks

#### Week 1: Setup
- [x] Install Laravel Breeze dengan Vue/Inertia stack
- [x] Configure database (SQLite for dev)
- [x] Create profiles migration
- [x] Setup Tailwind theme & dark mode
- [x] Create base layout components:
  - [x] Sidebar navigation
  - [x] Top navbar
  - [x] Content area

#### Week 2: Dashboard
- [x] Create Dashboard page layout
- [x] Add placeholder widgets:
  - [x] Monthly summary card
  - [x] Today's schedule card
  - [x] Recent activity
- [x] Implement Quick Action buttons
- [x] Add welcome message with user name

### Deliverables
- `/register` - User registration
- `/login` - User login
- `/dashboard` - Dashboard with layout

---

## Phase 2: Finance Module

**Duration**: Week 3-4

### Goals
- Complete finance CRUD
- Transaction tracking
- Category management
- Dashboard integration

### Tasks

#### Week 3: Core Features
- [x] Create migrations:
  - [x] finance_accounts
  - [x] finance_categories
  - [x] finance_transactions
- [x] Create Eloquent models
- [x] Create FinanceController
- [x] Create pages:
  - [x] Finance overview
  - [x] Transaction list
  - [x] Add transaction form

#### Week 4: Enhancement
- [x] Category CRUD
- [x] Account management
- [x] Weekly expense chart
- [x] Monthly summary
- [x] Dashboard widgets update
- [x] Default categories seeder

### Deliverables
- `/finance` - Finance overview
- `/finance/transactions` - Transaction list
- `/finance/categories` - Category management

---

## Phase 3: Schedule Module

**Duration**: Week 5-6

### Goals
- Calendar view
- Event CRUD
- Reminder system

### Tasks

#### Week 5: Core Features
- [x] Create migrations:
  - [x] calendars
  - [x] events
  - [x] event_reminders
- [x] Create Eloquent models
- [x] Create ScheduleController
- [x] Create Calendar view page
- [x] Implement event modal

#### Week 6: Enhancement
- [x] Event detail page
- [x] Reminder settings
- [x] Reminder job (queue)
- [x] Dashboard today's schedule
- [x] Week/Month view toggle

### Deliverables
- `/schedule` - Calendar view
- `/schedule/events/{id}` - Event detail
- Working reminder system

---

## Phase 4: Notes Module

**Duration**: Week 7-8

### Goals
- Notes CRUD
- Block-based editor
- Tags and search

### Tasks

#### Week 7: Core Features
- [x] Create migrations:
  - [x] notes
  - [x] note_blocks
  - [x] tags & note_tags
- [x] Create Eloquent models
- [x] Create NoteController
- [x] Notes list page
- [x] Basic note editor

#### Week 8: Enhancement
- [x] Block types:
  - [x] Text/paragraph
  - [x] Heading
  - [x] List
  - [x] Checkbox
  - [x] Code block
- [x] Tag management
- [x] Full-text search
- [x] Pin/archive notes

### Deliverables
- `/notes` - Notes list
- `/notes/{id}` - Note editor
- Search functionality

---

## Phase 5: Chat & AI Integration

**Duration**: Week 9-11

### Goals
- Web chat UI
- AI-powered responses
- Intent recognition
- Action execution

### Tasks

#### Week 9: Chat UI
- [x] Create migrations:
  - [x] chat_threads
  - [x] chat_messages
- [x] Create ChatController
- [x] Chat page with sidebar
- [x] Message input & display
- [x] Thread management

#### Week 10: AI Integration
- [x] Create AI service interface
- [x] Implement OpenAI provider
- [x] Implement Gemini provider
- [x] Intent parsing logic
- [x] Action execution framework

#### Week 11: Polish
- [x] Confirmation flow
- [x] User persona (call_preference)
- [x] Context awareness
- [x] Error handling
- [x] Integration tests

### Deliverables
- `/chat` - Chat interface
- Natural language command processing
- Finance/Schedule actions via chat

---

## Phase 6: Telegram Integration

**Duration**: Week 12-13

### Goals
- Working Telegram bot
- Account linking
- Full chat via Telegram

### Tasks

#### Week 12: Bot Setup
- [x] Create Telegram bot
- [x] Create migrations:
  - [x] external_identities
  - [x] integration_link_codes
- [x] Webhook controller
- [x] TelegramBotService
- [x] /start dan /link commands

#### Week 13: Full Integration
- [x] Process messages via ChatOrchestrator
- [x] Send responses
- [x] Reminder notifications
- [x] /unlink command
- [x] Error handling

### Deliverables
- Working Telegram bot
- Account linking
- All chat features via Telegram

---

## Phase 7: Polish & Optimization

**Duration**: Week 14

### Goals
- Production ready
- All tests passing
- Documentation complete

### Tasks

- [x] UI/UX review
- [x] Performance audit
- [x] Security audit
- [x] Write/update tests
- [x] Update documentation
- [x] Fix remaining bugs
- [x] Prepare for deployment

### Deliverables
- Complete test coverage
- Updated documentation
- Deployment ready

---

## Risk Mitigation

| Risk | Mitigation |
|------|------------|
| AI API costs | Set rate limits, implement caching |
| Complex block editor | Start with basic blocks, iterate |
| Telegram webhook issues | Fallback to polling for dev |
| Scope creep | Stick to MVP features per phase |

## Success Criteria

- [x] User registration and login works
- [x] Dashboard shows accurate data
- [x] Can add/view transactions
- [x] Can create/view events
- [x] Can create/edit notes
- [x] Chat responds intelligently
- [x] Telegram bot works end-to-end
- [x] All tests pass
- [x] Documentation is complete
