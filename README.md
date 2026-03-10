# Cobalt Strike Beacon Simulator

A testing tool to simulate Cobalt Strike beacon network traffic for validating NDR (Network Detection and Response) solutions.

## Overview

This tool mimics the HTTP POST traffic patterns of Cobalt Strike beacons to help security teams test whether their NDR solutions can detect Command & Control (C2) communications. It includes both a client that generates beacon-like traffic and a simple server to receive it.

## Components

- **cobalt_client.py** - Simulates Cobalt Strike beacon HTTP POST requests
- **fake_cobalt_server.py** - Simple HTTP server that mimics a C2 server endpoint

## Features

The client generates realistic Cobalt Strike beacon characteristics:
- ✅ POST requests to `/submit.php` endpoints
- ✅ `Content-Type: application/octet-stream` header
- ✅ Long Base64-encoded session cookies (128+ characters)
- ✅ Realistic User-Agent strings
- ✅ Binary payloads starting with null bytes
- ✅ Properly sized payloads (64+ bytes)

## Requirements

```bash
pip install requests
```

## Usage

### Start the Test Server

```bash
python fake_cobalt_server.py --port 8000
```

### Run the Client

**Basic usage (localhost):**
```bash
python cobalt_client.py
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

### Command-Line Arguments

#### Client (cobalt_client.py)

| Argument | Default | Description |
|----------|---------|-------------|
| `--domain` | `localhost:8000` | Target domain/IP with optional port |
| `--url` | - | Full URL (overrides --domain) |
| `--id` | `12345` | Numeric ID for query parameter |
| `--cookie-value` | (random) | Custom Base64 cookie value |

#### Server (fake_cobalt_server.py)

| Argument | Default | Description |
|----------|---------|-------------|
| `--port` | `8000` | Port to listen on |

## Testing Your NDR

1. Start the fake server on a test machine
2. Run the client from another machine (or the same one for local testing)
3. Check your NDR solution for alerts related to:
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
