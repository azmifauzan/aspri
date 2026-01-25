# Desain Database (Supabase Postgres)

Dokumen ini berisi rancangan skema database untuk ASPRI Next Gen.

Target:
- Multi-tenant per user (isolasi data berdasarkan `user_id`)
- Mendukung 5 modul: Dashboard, Chat, Note, Jadwal, Keuangan
- Integrasi Telegram (MVP) dan WhatsApp (rencana)

## Prinsip Desain

- Semua tabel domain memiliki `user_id` (UUID) untuk isolasi.
- Timestamp disimpan dalam UTC (`timestamptz`).
- Primary key menggunakan `uuid`.
- Agregasi dashboard diambil dari query terindeks (opsional: view/materialized view).

## Integrasi dengan Supabase Auth

Supabase menyimpan user pada `auth.users`. Aplikasi membuat tabel `profiles` pada skema `public`:

- `profiles.id` = `auth.users.id`
- `profiles` menyimpan metadata aplikasi (timezone, locale, dsb.), termasuk pengaturan persona asisten yang dipertahankan dari versi lama.

## Tipe/Enum

Disarankan membuat enum untuk konsistensi:

- `chat_channel`: `web`, `telegram`, `whatsapp`
- `note_status`: `active`, `archived`, `deleted`
- `finance_tx_type`: `income`, `expense`, `transfer`

## Tabel Inti (Ringkasan)

### Akun & Profil

- `profiles`
- `external_identities` (mapping Telegram/WhatsApp ke user)
- `integration_link_codes` (kode one-time untuk link Telegram)

### Chat

- `chat_threads`
- `chat_messages`

### Note

- `notes`
- `note_blocks` (penyimpanan konten sebagai blok berurutan)
- `note_versions` (riwayat versi)
- `tags`, `note_tags`
- `note_links` (backlink antar note)

### Jadwal

- `calendars`
- `events`
- `event_reminders`

### Keuangan

- `finance_accounts`
- `finance_categories`
- `finance_transactions`
- `finance_budgets` (opsional)

## Skema Detail (DDL Contoh)

Catatan: ini rancangan awal (bukan migrasi final). Implementasi migration dapat memakai Flyway/Liquibase di backend.

```sql
-- Extensions (opsional)
create extension if not exists pgcrypto;

-- Profiles
create table if not exists public.profiles (
  id uuid primary key references auth.users(id) on delete cascade,
  email text,
  display_name text,
  -- Persona & preferensi komunikasi (dipertahankan dari versi lama)
  call_preference text, -- bagaimana user ingin dipanggil (mis. "Kak", "Bapak", "Dina")
  aspri_name text, -- nama asisten yang disukai user (default: "ASPRI")
  aspri_persona text, -- deskripsi gaya komunikasi/personalitas (default: "helpful")

  -- Personalisasi (opsional; ada di versi lama)
  birth_day int,
  birth_month int,

  timezone text not null default 'Asia/Jakarta',
  locale text not null default 'id-ID',
  theme text not null default 'light' check (theme in ('light','dark')),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

-- External identities (Telegram/WhatsApp)
create table if not exists public.external_identities (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references public.profiles(id) on delete cascade,
  provider text not null check (provider in ('telegram','whatsapp')),
  provider_user_id text not null,
  provider_chat_id text,
  provider_username text,
  is_verified boolean not null default true,
  created_at timestamptz not null default now(),
  unique (provider, provider_user_id)
);

-- One-time linking codes
create table if not exists public.integration_link_codes (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references public.profiles(id) on delete cascade,
  provider text not null check (provider in ('telegram','whatsapp')),
  code text not null,
  expires_at timestamptz not null,
  used_at timestamptz,
  created_at timestamptz not null default now(),
  unique (provider, code)
);

-- Chat threads
create table if not exists public.chat_threads (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references public.profiles(id) on delete cascade,
  title text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

-- Chat messages
create table if not exists public.chat_messages (
  id uuid primary key default gen_random_uuid(),
  thread_id uuid not null references public.chat_threads(id) on delete cascade,
  user_id uuid not null references public.profiles(id) on delete cascade,
  channel text not null check (channel in ('web','telegram','whatsapp')),
  direction text not null check (direction in ('user','assistant','system')),
  external_message_id text,
  content text not null,
  metadata jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now()
);

create index if not exists idx_chat_messages_user_created
  on public.chat_messages (user_id, created_at desc);

-- Notes
create table if not exists public.notes (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references public.profiles(id) on delete cascade,
  title text,
  status text not null default 'active' check (status in ('active','archived','deleted')),
  pinned boolean not null default false,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create index if not exists idx_notes_user_updated
  on public.notes (user_id, updated_at desc);

-- Note blocks (advanced content)
create table if not exists public.note_blocks (
  id uuid primary key default gen_random_uuid(),
  note_id uuid not null references public.notes(id) on delete cascade,
  user_id uuid not null references public.profiles(id) on delete cascade,
  position int not null,
  block_type text not null,
  data jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique(note_id, position)
);

create index if not exists idx_note_blocks_note
  on public.note_blocks (note_id, position);

-- Note versions (history)
create table if not exists public.note_versions (
  id uuid primary key default gen_random_uuid(),
  note_id uuid not null references public.notes(id) on delete cascade,
  user_id uuid not null references public.profiles(id) on delete cascade,
  version int not null,
  snapshot jsonb not null,
  created_at timestamptz not null default now(),
  unique(note_id, version)
);

-- Tags
create table if not exists public.tags (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references public.profiles(id) on delete cascade,
  name text not null,
  created_at timestamptz not null default now(),
  unique(user_id, name)
);

create table if not exists public.note_tags (
  note_id uuid not null references public.notes(id) on delete cascade,
  tag_id uuid not null references public.tags(id) on delete cascade,
  user_id uuid not null references public.profiles(id) on delete cascade,
  primary key (note_id, tag_id)
);

-- Backlinks
create table if not exists public.note_links (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references public.profiles(id) on delete cascade,
  from_note_id uuid not null references public.notes(id) on delete cascade,
  to_note_id uuid not null references public.notes(id) on delete cascade,
  created_at timestamptz not null default now(),
  unique(user_id, from_note_id, to_note_id)
);

-- Calendars
create table if not exists public.calendars (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references public.profiles(id) on delete cascade,
  name text not null,
  color text,
  created_at timestamptz not null default now()
);

-- Events
create table if not exists public.events (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references public.profiles(id) on delete cascade,
  calendar_id uuid references public.calendars(id) on delete set null,
  title text not null,
  description text,
  start_at timestamptz not null,
  end_at timestamptz,
  all_day boolean not null default false,
  location text,
  rrule text, -- optional RFC5545 RRULE
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create index if not exists idx_events_user_start
  on public.events (user_id, start_at);

-- Event reminders
create table if not exists public.event_reminders (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references public.profiles(id) on delete cascade,
  event_id uuid not null references public.events(id) on delete cascade,
  remind_at timestamptz not null,
  channel text not null check (channel in ('app','telegram','whatsapp')),
  sent_at timestamptz,
  created_at timestamptz not null default now()
);

create index if not exists idx_event_reminders_due
  on public.event_reminders (sent_at, remind_at);

-- Finance accounts
create table if not exists public.finance_accounts (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references public.profiles(id) on delete cascade,
  name text not null,
  currency text not null default 'IDR',
  created_at timestamptz not null default now(),
  unique(user_id, name)
);

-- Finance categories
create table if not exists public.finance_categories (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references public.profiles(id) on delete cascade,
  name text not null,
  tx_type text not null check (tx_type in ('income','expense')),
  created_at timestamptz not null default now(),
  unique(user_id, tx_type, name)
);

-- Transactions
create table if not exists public.finance_transactions (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references public.profiles(id) on delete cascade,
  account_id uuid references public.finance_accounts(id) on delete set null,
  category_id uuid references public.finance_categories(id) on delete set null,
  tx_type text not null check (tx_type in ('income','expense','transfer')),
  amount numeric(18,2) not null,
  occurred_at timestamptz not null,
  note text,
  metadata jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now()
);

create index if not exists idx_finance_tx_user_time
  on public.finance_transactions (user_id, occurred_at desc);
```

## RLS (Row Level Security) – Opsi

Jika suatu saat frontend perlu akses langsung ke Supabase Postgres (tanpa backend), aktifkan RLS dan policy per user.

Untuk pola akses saat ini (frontend → backend → DB via JDBC), RLS bukan satu-satunya kontrol; backend tetap wajib melakukan otorisasi dan filter `user_id`.

Contoh policy (untuk akses langsung via Supabase JWT):

```sql
alter table public.notes enable row level security;

create policy "notes_select_own" on public.notes
  for select
  using (auth.uid() = user_id);

create policy "notes_insert_own" on public.notes
  for insert
  with check (auth.uid() = user_id);

create policy "notes_update_own" on public.notes
  for update
  using (auth.uid() = user_id)
  with check (auth.uid() = user_id);

create policy "notes_delete_own" on public.notes
  for delete
  using (auth.uid() = user_id);
```

## Catatan Dashboard Aggregation

Untuk chart yang sering dipakai, pertimbangkan:
- index yang tepat (lihat `idx_events_user_start`, `idx_finance_tx_user_time`)
- view/materialized view untuk ringkasan mingguan/bulanan

