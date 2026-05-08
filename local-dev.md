# Development Environment

This project currently runs with:

- Laravel API served by Herd at `http://api.calvertchess.test`
- Next.js frontend served by `npm run dev` (preferred URL: `http://web.calvertchess.test`)
- PostgreSQL running locally and managed through pgAdmin 4
- Optional Docker services for Redis, Docker Postgres, and the future engine worker

## Start Up

### 1. Start Herd

Open Laravel Herd and make sure it is running.

The API should already be linked as:

```text
http://api.calvertchess.test
```

Add local hosts entries so both app domains resolve:

```text
127.0.0.1 web.calvertchess.test
127.0.0.1 api.calvertchess.test
```

### 2. Start PostgreSQL

Start your local PostgreSQL server and confirm the database exists:

```text
calvert_chess_coach_journal
```

The Laravel API expects these local values in `apps/api/.env`:

```env
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=calvert_chess_coach_journal
DB_USERNAME=postgres
DB_PASSWORD=<your local Postgres password>
```

### 3. Start the Next.js frontend

From the repo root:

```powershell
cd "apps\web"
npm run dev
```

Then open:

```text
http://web.calvertchess.test
```

### 4. Check the Laravel API

Open:

```text
http://api.calvertchess.test
```

Useful API commands:

```powershell
cd "apps\api"
php artisan migrate:status
php artisan test
```

## Optional Docker Services

Use this if you want Docker-managed Redis/PostgreSQL or the future engine worker.

From the repo root:

```powershell
docker compose up -d db redis
```

Run the future engine worker profile:

```powershell
docker compose --profile engine up
```

Run the Docker API instead of Herd:

```powershell
docker compose --profile docker-api up
```

## Wind Down

### 1. Stop Next.js

In the terminal running `npm run dev`, press:

```text
Ctrl+C
```

### 2. Stop Docker services

If you started Docker services, run from the repo root:

```powershell
docker compose down
```

To also remove Docker volumes, including Docker-managed Postgres data:

```powershell
docker compose down -v
```

Only use `-v` when you are happy to delete Docker database data.

### 3. Stop local services

- Stop Herd if you are done with PHP sites.
- Stop PostgreSQL if you do not want it running in the background.

## Quick Daily Commands

Start frontend:

```powershell
cd "F:\CalvertChess\Calvert Chess Coach Journal\apps\web"
npm run dev
```

Check backend:

```powershell
cd "F:\CalvertChess\Calvert Chess Coach Journal\apps\api"
php artisan migrate:status
```

Run tests:

```powershell
cd "F:\CalvertChess\Calvert Chess Coach Journal\apps\api"
php artisan test

cd "F:\CalvertChess\Calvert Chess Coach Journal\apps\web"
npm run lint
npm run build
```
