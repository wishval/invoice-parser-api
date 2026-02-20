# Invoice Parser API

A Laravel microservice that parses PDF invoices using OpenAI GPT-4o Vision and returns structured JSON data.

![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?logo=laravel&logoColor=white)
![OpenAI](https://img.shields.io/badge/OpenAI-GPT--4o-412991?logo=openai&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green)

## Features

- **PDF Upload with Async Processing** -- Upload invoices and get results via polling; heavy work runs in background queues
- **AI-Powered Data Extraction** -- Extracts vendor, customer, line items, totals, dates, and invoice numbers using GPT-4o Vision with Structured Outputs
- **Confidence Scoring** -- Each extracted field includes a confidence score so you know how reliable the data is
- **RESTful CRUD with Pagination** -- List, show, download, and delete invoices with filtering by status and paginated results
- **Token-Based Authentication** -- Secure API access via Laravel Sanctum bearer tokens
- **Auto-Generated OpenAPI Documentation** -- Interactive API docs at `/docs/api` powered by Scramble

## Architecture

The system uses an asynchronous job chain to process invoices. When a PDF is uploaded, it is stored and a background pipeline is dispatched:

```
Upload PDF --> Queue --> ProcessInvoice --> ConvertPdfToImages --> ParseInvoiceWithAI --> SaveParsedData --> CleanupTempFiles
```

1. **ProcessInvoice** -- Validates the stored PDF and sets status to `processing`
2. **ConvertPdfToImages** -- Uses Ghostscript (via `spatie/pdf-to-image`) to convert each PDF page to a PNG image
3. **ParseInvoiceWithAI** -- Sends page images to OpenAI GPT-4o Vision with Structured Outputs for reliable JSON extraction
4. **SaveParsedData** -- Validates the AI response and writes vendor, customer, line items, and totals to the database in a transaction
5. **CleanupTempFiles** -- Removes temporary image files and manifest data

Each job in the chain is independently retryable. If any step fails, the invoice status is set to `failed` with an error message.

### Tech Stack

- **Runtime:** PHP 8.3, Laravel 11
- **Database:** SQLite (default) / MySQL 8
- **Queue & Cache:** Redis 7
- **Web Server:** Nginx 1.24
- **AI:** OpenAI GPT-4o (gpt-4o-2024-08-06) with Structured Outputs
- **PDF Processing:** Ghostscript + spatie/pdf-to-image
- **Auth:** Laravel Sanctum
- **API Docs:** dedoc/scramble (OpenAPI 3.1.0)
- **Containers:** Docker Compose

## Quick Start

```bash
# Clone the repository
git clone <repository-url>
cd invoice-parser-api

# Copy environment file and set your OpenAI API key
cp .env.example .env
# Edit .env and set OPENAI_API_KEY=sk-...

# Start the Docker stack
docker compose up -d

# Generate application key
docker compose exec app php artisan key:generate

# Run database migrations
docker compose exec app php artisan migrate

# Seed test user (email: test@example.com, password: password)
docker compose exec app php artisan db:seed

# Start the queue worker (in a separate terminal)
docker compose exec app php artisan queue:work redis --queue=parse
```

The API is now available at `http://localhost:8000/api/v1`.

## API Usage

### Authentication

**Login to get a bearer token:**

```bash
curl -X POST http://localhost:8000/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}'
```

Response:

```json
{
  "token": "1|abc123...",
  "user": {
    "id": 1,
    "name": "Test User",
    "email": "test@example.com"
  }
}
```

### Upload an Invoice

```bash
curl -X POST http://localhost:8000/api/v1/invoices \
  -H "Authorization: Bearer {token}" \
  -F "pdf=@/path/to/invoice.pdf"
```

Returns `202 Accepted` with the invoice in `pending` status. The invoice will be processed asynchronously.

### List Invoices

```bash
# List all invoices (paginated)
curl http://localhost:8000/api/v1/invoices \
  -H "Authorization: Bearer {token}"

# Filter by status
curl "http://localhost:8000/api/v1/invoices?status=completed" \
  -H "Authorization: Bearer {token}"

# Paginate
curl "http://localhost:8000/api/v1/invoices?page=2&per_page=10" \
  -H "Authorization: Bearer {token}"
```

### Show Invoice Details

```bash
curl http://localhost:8000/api/v1/invoices/1 \
  -H "Authorization: Bearer {token}"
```

Returns the full invoice with extracted data: vendor info, customer info, line items, totals, and confidence scores.

### Download Original PDF

```bash
curl http://localhost:8000/api/v1/invoices/1/download \
  -H "Authorization: Bearer {token}" \
  -o invoice.pdf
```

### Delete an Invoice

```bash
curl -X DELETE http://localhost:8000/api/v1/invoices/1 \
  -H "Authorization: Bearer {token}"
```

## API Endpoints

| Method | Endpoint                         | Description              | Auth     |
|--------|----------------------------------|--------------------------|----------|
| GET    | `/api/v1/health`                 | Health check             | No       |
| POST   | `/api/v1/login`                  | Authenticate & get token | No       |
| POST   | `/api/v1/logout`                 | Revoke current token     | Bearer   |
| GET    | `/api/v1/user`                   | Get authenticated user   | Bearer   |
| POST   | `/api/v1/invoices`               | Upload PDF invoice       | Bearer   |
| GET    | `/api/v1/invoices`               | List invoices            | Bearer   |
| GET    | `/api/v1/invoices/{id}`          | Show invoice details     | Bearer   |
| GET    | `/api/v1/invoices/{id}/download` | Download original PDF    | Bearer   |
| DELETE | `/api/v1/invoices/{id}`          | Delete invoice           | Bearer   |

## API Documentation

Interactive OpenAPI documentation is available when the application is running:

- **Docs UI:** [http://localhost:8000/docs/api](http://localhost:8000/docs/api)
- **OpenAPI JSON:** [http://localhost:8000/docs/api.json](http://localhost:8000/docs/api.json)

The documentation is auto-generated from route definitions, form requests, and API resources using [Scramble](https://scramble.dedoc.co/).

## Environment Variables

| Variable             | Description                          | Default              |
|----------------------|--------------------------------------|----------------------|
| `APP_KEY`            | Application encryption key           | *(generated)*        |
| `APP_ENV`            | Application environment              | `local`              |
| `APP_DEBUG`          | Enable debug mode                    | `true`               |
| `APP_URL`            | Application URL                      | `http://localhost:8000` |
| `DB_CONNECTION`      | Database driver                      | `sqlite`             |
| `DB_HOST`            | Database host                        | `127.0.0.1`          |
| `DB_PORT`            | Database port                        | `3306`               |
| `DB_DATABASE`        | Database name                        | `laravel`            |
| `DB_USERNAME`        | Database username                    | `root`               |
| `DB_PASSWORD`        | Database password                    | *(empty)*            |
| `REDIS_HOST`         | Redis host                           | `redis`              |
| `REDIS_PORT`         | Redis port                           | `6379`               |
| `QUEUE_CONNECTION`   | Queue driver                         | `redis`              |
| `CACHE_STORE`        | Cache driver                         | `redis`              |
| `SESSION_DRIVER`     | Session driver                       | `redis`              |
| `OPENAI_API_KEY`     | OpenAI API key **(required)**        | *(none)*             |
| `OPENAI_ORGANIZATION`| OpenAI organization ID *(optional)*  | *(none)*             |

## Testing

```bash
# Run the full test suite
docker compose exec app php artisan test

# Run with coverage
docker compose exec app php artisan test --coverage
```

## License

This project is open-sourced software licensed under the [MIT License](https://opensource.org/licenses/MIT).
