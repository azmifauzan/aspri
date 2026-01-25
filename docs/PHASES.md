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
- ✅ Working Laravel 12 application
- ✅ User authentication (register/login)
- ✅ Base layout with sidebar
- ✅ Dashboard placeholder

### Tasks

#### Week 1: Setup
- [ ] Install Laravel Breeze dengan Vue/Inertia stack
- [ ] Configure database (SQLite for dev)
- [ ] Create profiles migration
- [ ] Setup Tailwind theme & dark mode
- [ ] Create base layout components:
  - [ ] Sidebar navigation
  - [ ] Top navbar
  - [ ] Content area

#### Week 2: Dashboard
- [ ] Create Dashboard page layout
- [ ] Add placeholder widgets:
  - [ ] Monthly summary card
  - [ ] Today's schedule card
  - [ ] Recent activity
- [ ] Implement Quick Action buttons
- [ ] Add welcome message with user name

### Deliverables
- `/register` - User registration
- `/login` - User login
- `/dashboard` - Dashboard with layout

---

## Phase 2: Finance Module

**Duration**: Week 3-4

### Goals
- ✅ Complete finance CRUD
- ✅ Transaction tracking
- ✅ Category management
- ✅ Dashboard integration

### Tasks

#### Week 3: Core Features
- [ ] Create migrations:
  - [ ] finance_accounts
  - [ ] finance_categories
  - [ ] finance_transactions
- [ ] Create Eloquent models
- [ ] Create FinanceController
- [ ] Create pages:
  - [ ] Finance overview
  - [ ] Transaction list
  - [ ] Add transaction form

#### Week 4: Enhancement
- [ ] Category CRUD
- [ ] Account management
- [ ] Weekly expense chart
- [ ] Monthly summary
- [ ] Dashboard widgets update
- [ ] Default categories seeder

### Deliverables
- `/finance` - Finance overview
- `/finance/transactions` - Transaction list
- `/finance/categories` - Category management

---

## Phase 3: Schedule Module

**Duration**: Week 5-6

### Goals
- ✅ Calendar view
- ✅ Event CRUD
- ✅ Reminder system

### Tasks

#### Week 5: Core Features
- [ ] Create migrations:
  - [ ] calendars
  - [ ] events
  - [ ] event_reminders
- [ ] Create Eloquent models
- [ ] Create ScheduleController
- [ ] Create Calendar view page
- [ ] Implement event modal

#### Week 6: Enhancement
- [ ] Event detail page
- [ ] Reminder settings
- [ ] Reminder job (queue)
- [ ] Dashboard today's schedule
- [ ] Week/Month view toggle

### Deliverables
- `/schedule` - Calendar view
- `/schedule/events/{id}` - Event detail
- Working reminder system

---

## Phase 4: Notes Module

**Duration**: Week 7-8

### Goals
- ✅ Notes CRUD
- ✅ Block-based editor
- ✅ Tags and search

### Tasks

#### Week 7: Core Features
- [ ] Create migrations:
  - [ ] notes
  - [ ] note_blocks
  - [ ] tags & note_tags
- [ ] Create Eloquent models
- [ ] Create NoteController
- [ ] Notes list page
- [ ] Basic note editor

#### Week 8: Enhancement
- [ ] Block types:
  - [ ] Text/paragraph
  - [ ] Heading
  - [ ] List
  - [ ] Checkbox
  - [ ] Code block
- [ ] Tag management
- [ ] Full-text search
- [ ] Pin/archive notes

### Deliverables
- `/notes` - Notes list
- `/notes/{id}` - Note editor
- Search functionality

---

## Phase 5: Chat & AI Integration

**Duration**: Week 9-11

### Goals
- ✅ Web chat UI
- ✅ AI-powered responses
- ✅ Intent recognition
- ✅ Action execution

### Tasks

#### Week 9: Chat UI
- [ ] Create migrations:
  - [ ] chat_threads
  - [ ] chat_messages
- [ ] Create ChatController
- [ ] Chat page with sidebar
- [ ] Message input & display
- [ ] Thread management

#### Week 10: AI Integration
- [ ] Create AI service interface
- [ ] Implement OpenAI provider
- [ ] Implement Gemini provider
- [ ] Intent parsing logic
- [ ] Action execution framework

#### Week 11: Polish
- [ ] Confirmation flow
- [ ] User persona (call_preference)
- [ ] Context awareness
- [ ] Error handling
- [ ] Integration tests

### Deliverables
- `/chat` - Chat interface
- Natural language command processing
- Finance/Schedule actions via chat

---

## Phase 6: Telegram Integration

**Duration**: Week 12-13

### Goals
- ✅ Working Telegram bot
- ✅ Account linking
- ✅ Full chat via Telegram

### Tasks

#### Week 12: Bot Setup
- [ ] Create Telegram bot
- [ ] Create migrations:
  - [ ] external_identities
  - [ ] integration_link_codes
- [ ] Webhook controller
- [ ] TelegramBotService
- [ ] /start dan /link commands

#### Week 13: Full Integration
- [ ] Process messages via ChatOrchestrator
- [ ] Send responses
- [ ] Reminder notifications
- [ ] /unlink command
- [ ] Error handling

### Deliverables
- Working Telegram bot
- Account linking
- All chat features via Telegram

---

## Phase 7: Polish & Optimization

**Duration**: Week 14

### Goals
- ✅ Production ready
- ✅ All tests passing
- ✅ Documentation complete

### Tasks

- [ ] UI/UX review
- [ ] Performance audit
- [ ] Security audit
- [ ] Write/update tests
- [ ] Update documentation
- [ ] Fix remaining bugs
- [ ] Prepare for deployment

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

- [ ] User registration and login works
- [ ] Dashboard shows accurate data
- [ ] Can add/view transactions
- [ ] Can create/view events
- [ ] Can create/edit notes
- [ ] Chat responds intelligently
- [ ] Telegram bot works end-to-end
- [ ] All tests pass
- [ ] Documentation is complete
