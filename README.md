# Cobalt Strike Beacon Simulator

A testing tool to simulate Cobalt Strike beacon network traffic for validating NDR (Network Detection and Response) solutions.

## Overview

This tool mimics the HTTP POST traffic patterns of Cobalt Strike beacons to help security teams test whether their NDR solutions can detect Command & Control (C2) communications. It includes both a client that generates beacon-like traffic and a simple server to receive it.

## Components

- **cobalt_client.py** - Python client that simulates Cobalt Strike beacon HTTP POST requests with single or beacon mode
- **cobalt_client.ps1** - PowerShell client with identical functionality for Windows environments
- **fake_cobalt_server.py** - Simple Python HTTP server that mimics a C2 server endpoint
- **submit.php** - PHP endpoint that simulates realistic C2 server responses

## Features

The client generates realistic Cobalt Strike beacon characteristics:
- ✅ POST requests to `/submit.php` endpoints
- ✅ `Content-Type: application/octet-stream` header
- ✅ Long Base64-encoded session cookies (128+ characters)
- ✅ Realistic User-Agent strings
- ✅ Binary payloads starting with null 
- ✅ Beacon mode with configurable intervals and jitter
- ✅ Continuous operation for extended testing periodsbytes
- ✅ Properly sized payloads (64+ bytes)

## Requirements

**Python Client:**
```bash
pip install requests
```

**PowerShell Client:**
- PowerShell 5.1 or later (built into Windows)
- No additional packages required

## Usage

### Start the Test Server

**Option 1: Using the Python fake server**
```bash
python fake_cobalt_server.py --port 8000
```

**Option 2: Using the PHP endpoint**
```bash
php -S 0.0.0.0:8080 submit.php
```

### Run the Client

#### Python Client

**Single request (one-time check-in):**
```bash
python cobalt_client.py
```

**Beacon mode (continuous periodic check-ins):**
```bash
# Run for 5 minutes with 60 second intervals (default)
python cobalt_client.py --beacon-mode

# Custom duration and interval
python cobalt_client.py --beacon-mode --duration 10 --interval 30

# With jitter for realistic randomization (±20% variance)
python cobalt_client.py --beacon-mode --interval 60 --jitter 20

# Full example: 15 minutes, 45 second intervals, 10% jitter
python cobalt_client.py --beacon-mode --domain 192.168.1.100:8080 \
                       --duration 15 --interval 45 --jitter 10
```

**Specify custom domain:**
```bash
python cobalt_client.py --domain 192.168.1.100:8080
```

**Full URL override:**
```bash
python cobalt_client.py --url http://example.com/api/submit.php?id=99999
```

**Custom cookie value:**
```bash
python cobalt_client.py --domain target.local --cookie-value dGVzdENvb2tpZVZhbHVl
```

#### Python Client (cobalt_client.py)

| Argument | Default | Description |
|----------|---------|-------------|
| `--domain` | `localhost:8000` | Target domain/IP with optional port |
| `--url` | - | Full URL (overrides --domain) |
| `--id` | `12345` | Numeric ID for query parameter |
| `--cookie-value` | (random) | Custom Base64 cookie value |
| `--beacon-mode` | disabled | Enable continuous beacon mode |
| `--duration` | `5.0` | Duration to run beacon mode (minutes) |
| `--interval` | `60.0` | Sleep interval between beacons (seconds) |
| `--jitter` | `0.0` | Random variance for interval (0-100%) |

#### PowerShell Client (cobalt_client.ps1)

| Parameter | Default | Description |
|----------|---------|-------------|
| `-Domain` | `localhost:8000` | Target domain/IP with optional port |
| `-Url` | - | Full URL (overrides -Domain) |
| `-Id` | `12345` | Numeric ID for query parameter |
| `-CookieValue` | (random) | Custom Base64 cookie value |
| `-BeaconMode` | disabled | Enable continuous beacon mode |
| `-Duration` | `5.0` | Duration to run beacon mode (minutes) |
| `-Interval` | `60.0` | Sleep interval between beacons (seconds) |
| `-J
# Custom duration and interval
.\cobalt_client.ps1 -BeaconMode -Duration 10 -Interval 30

# With jitter
.\cobalt_client.ps1 -BeaconMode -Domain "192.168.1.100:8080" -Interval 60 -Jitter 20

# Full example
.\cobalt_client.ps1 -BeaconMode -Domain "target.local:8080" -Duration 15 -Interval 45 -Jitter 10
```

**Custom URL:**
```powershell
.\cobalt_client.ps1 -Url "http://example.com/api/submit.php?id=99999"
```

**Get help:**
```powershell
Get-Help .\cobalt_client.ps1 -Full
```

### Command-Line Arguments

#### Client (cobalt_client.py)

| Argument | Default | Description |
|----------|---------|-------------|
| `--domain` | `localhost:8000` | Target domain/IP with optional port |
| `--url` | - | Full URL (overrides --domain) |
| `--id` | `12345` | Numeric ID for query parameter |
| `--cookie-value` | (random) | Custom Base64 cookie value |
| `--beacon-mode` | disabled | Enable continuous beacon mode |
| `--duration` | `5.0` | Duration to run beacon mode (minutes) |
| `--interval` | `60.0` | Sleep interval between beacons (seconds) |
| `--jitter` | `0.0` | Random variance for interval (0-100%) |

#### Server (fake_cobalt_server.py)

| Argument | Default | Description |
|----------|---------|-------------|
### Single Request Testing
1. Start the fake server on a test machine
2. Run the client once to generate a single beacon check-in
3. Check your NDR solution for immediate alerts

### Beacon Mode Testing (Recommended)
1. Start the server on a test machine (or use PHP endpoint)
2. Run the client in beacon mode with realistic timing:
   ```bash
   python cobalt_client.py --beacon-mode --domain <target> \
                          --duration 10 --interval 60 --jitter 20
   ```
3. Monitor your NDR for:
   - Detection of the initial beacon
   - Pattern recognition over time
   - Alert generation for sustained C2 activity
   - Time-to-detection metrics

### What to Look For

Your NDR should flag traffic with these characteristics:
- Cobalt Strike beacon activity signatures
- C2 communication patterns
- Suspicious HTTP POST with binary payloads
- Long Base64-encoded cookies
- Periodic check-in patterns (in beacon mode)
- POST requests with octet-stream to .php endpointalerts related to:
   - Cobalt Strike beacon activity
   - C2 communication patterns
   - Suspicious HTTP POST with binary payloads
   - Long Base64-encoded cookies

## Detection Indicators

Your NDR should flag traffic with these characteristics:
- POST to `.php` endpoints with query parameters
- `application/octet-stream` content type
- Cookies with long Base64 values (100+ chars)
- Binary payloads starting with null bytes
- Payload sizes between 48-256 bytes
- Combination of User-Agent + octet-stream + cookie patterns

## Disclaimer

⚠️ **For authorized security testing only.** This tool is designed for testing your own network security controls. Do not use against systems you don't own or have explicit permission to test.

## License

This is a security testing tool for educational and authorized testing purposes only.
