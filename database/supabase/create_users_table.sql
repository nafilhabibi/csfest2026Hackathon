create table if not exists public.users (
    id uuid primary key default gen_random_uuid(),
    name text not null,
    email text not null,
    password text not null,
    remember_token text,
    role text not null default 'participant',
    email_verified_at timestamptz,
    created_at timestamptz not null default timezone('utc', now()),
    updated_at timestamptz not null default timezone('utc', now())
);

create unique index if not exists users_email_unique
    on public.users (lower(email));

alter table public.users
    add column if not exists role text not null default 'participant';

alter table public.users
    drop constraint if exists users_role_check;

alter table public.users
    add constraint users_role_check
    check (role in ('admin', 'participant'));

alter table public.users enable row level security;

create or replace function public.set_users_updated_at()
returns trigger
language plpgsql
as $$
begin
    new.updated_at = timezone('utc', now());
    return new;
end;
$$;

drop trigger if exists set_users_updated_at on public.users;

create trigger set_users_updated_at
before update on public.users
for each row
execute function public.set_users_updated_at();
