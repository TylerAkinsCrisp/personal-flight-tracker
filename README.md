# Flight Tracker

A personal flight tracking website for sharing trip information with family using clickable FlightAware tracking links.

## Features

- **Public Site**: Shows current trip with departure and return flight segments
- **Live Tracking Links**: "Track Live on FlightAware" button for each flight segment
- **Admin Panel**: Full CRUD for trips and flight segments
- **FlightAware URL Parser**: Paste a FlightAware URL to auto-populate flight details
- **Mobile Friendly**: Responsive Bootstrap 5 dark theme
- **Security**: CSRF protection, rate limiting, session hardening

## Requirements

- PHP 7.4+ with PDO MySQL extension
- MySQL 5.7+ or MariaDB 10.3+
- Apache with mod_rewrite enabled
- HTTPS (recommended)

## Installation

### 1. Upload Files

Upload all files to your `public_html` directory (or subdirectory).

### 2. Create Environment File

Copy `.env.example` to `.env` and fill in your values:

```bash
cp .env.example .env
```

Edit `.env`:

```ini
SITE_NAME=My Flight Tracker

DB_HOST=localhost
DB_NAME=your_database_name
DB_USER=your_database_user
DB_PASS=your_database_password

ADMIN_SECRET_CODE=your_secure_secret_code_at_least_32_chars
```

**Important**: Generate a strong secret code for admin access. Use a password generator for 32+ random characters.

### 3. Run Database Installation

1. Navigate to `https://yourdomain.com/setup/install.php`
2. Click "Install Database Tables"
3. **DELETE `setup/install.php` after successful installation!**

### 4. Access Admin Panel

Go to `https://yourdomain.com/admin/` and enter your secret code.

## File Structure

```
├── config/
│   ├── config.php          # Central configuration (loads from .env)
│   ├── database.php        # PDO database wrapper
│   └── security.php        # CSRF, session, rate limiting helpers
├── includes/
│   └── functions.php       # Utility functions
├── admin/
│   ├── index.php           # Admin dashboard
│   ├── login.php           # Admin login
│   ├── logout.php          # Admin logout
│   ├── trip-edit.php       # Create/edit trips
│   └── api/                # Admin API endpoints
├── api/
│   └── trips.php           # Public trips API
├── js/
│   └── admin.js            # Admin panel JavaScript
├── setup/
│   ├── schema.sql          # Database schema
│   └── install.php         # One-time installer (DELETE AFTER USE)
├── index.php               # Public homepage
├── .htaccess               # Apache configuration
├── .env.example            # Environment template
└── .gitignore              # Git ignore rules
```

## Adding a Trip

### 1. Create Trip

1. Go to Admin Panel → "Add New Trip"
2. Fill in trip details (name, destination, dates)
3. Click "Create Trip"

### 2. Add Flight Segments

For each flight segment:

1. Click "Add Segment" under Departure or Return
2. **Option A**: Paste a FlightAware URL and click "Parse" to auto-fill
3. **Option B**: Manually enter flight details
4. Fill in remaining fields (times, timezones)
5. Click "Create Trip" or "Update Trip"

### Using FlightAware URLs

FlightAware URLs contain flight information that can be auto-parsed:

```
https://www.flightaware.com/live/flight/AAL4046/history/20260116/1220Z/KXNA/KDFW
                                         │       │        │     │    │
                                         │       │        │     │    └─ Arrival airport
                                         │       │        │     └────── Departure airport
                                         │       │        └──────────── Time (UTC)
                                         │       └───────────────────── Date
                                         └───────────────────────────── Flight number
```

The parser extracts:
- Flight number (AAL4046 → AA 4046)
- Airline name (American Airlines)
- Departure/arrival airports (XNA, DFW)
- Date and scheduled departure time (converted to local timezone)

### Track Live Links

The public page shows a "Track Live on FlightAware" button for each segment on the day of departure or later.

- If a segment has a saved `flightaware_url`, that URL is used.
- If not, a fallback URL is built from the segment's `flight_number`.

## Security Features

### CSRF Protection
All admin forms include CSRF tokens validated on submission.

### Rate Limiting
- Login: 5 attempts per 15 minutes per IP
- API: 100 requests per hour per session

### Session Hardening
- Session ID regenerated on login
- Secure cookie flags (HttpOnly, SameSite=Strict)
- IP binding (session invalidated if IP changes)
- 1-hour idle timeout

### Content Security Policy
CSP header restricts resources to:
- Self
- Bootstrap CDN (cdn.jsdelivr.net)

## API

### GET /api/trips.php

Returns current and past trips.

**Parameters:**
- `include_past=true` - Include past trips in response
- `id=123` - Get specific trip by ID

## Troubleshooting

### Database Connection Failed
- Verify credentials in `.env` file
- Check database exists
- Ensure MySQL service is running

### 403 Forbidden on Admin
- Check `.htaccess` is uploaded correctly
- Verify mod_rewrite is enabled
- Check file permissions (644 for files, 755 for directories)

### FlightAware Parser Not Working
- Ensure URL is from flightaware.com
- Check URL format matches expected pattern
- Try the full URL with /history/ path

## Development

### Local Testing

1. Set up local PHP/MySQL environment
2. Copy `.env.example` to `.env` with local credentials
3. Run `setup/install.php` to create tables
4. Access via local server

### Pre-commit Check

Run the security check script before committing:

```bash
./scripts/check-secrets.sh
```

## License

MIT
