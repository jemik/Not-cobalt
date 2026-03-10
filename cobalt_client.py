"""
Client script that sends a crafted POST request mimicking a Cobalt Strike Beacon.

The request goes to a configurable URL (default http://localhost:8000/submit.php?id=12345),
sets a Base64-encoded cookie value, defines the Content-Type as `application/octet-stream`,
and includes a payload beginning with three null bytes.  This script is intended for
testing network detection rules that look for C2 traffic patterns similar to Cobalt Strike.

Usage:

    python cobalt_client.py --url http://localhost:8000/submit.php?id=12345 \
                           --id 12345 \
                           --cookie-value dGVzdFN0cmluZw==

If the cookie value is not provided, a default Base64 value is used.  The numeric ID
is appended to the URL automatically when `--id` is specified.
"""

import argparse
import base64
import os
import sys
import time
from datetime import datetime, timedelta
from typing import Optional

import requests


def build_payload() -> bytes:
    """Construct the binary payload starting with four null bytes.

    Real Cobalt Strike beacons send payloads that are:
    - Starting with 4 null bytes (counter/length prefix)
    - Minimum 48-92 bytes in length
    - Contains encrypted data (appears random)
    
    This generates a realistic-sized payload for NDR detection.
    """
    # Start with 4 null bytes (typical Cobalt Strike pattern)
    payload = b'\x00\x00\x00\x00'
    
    # Add random-looking encrypted data (minimum 48 bytes total)
    # Real beacons encrypt data, so we simulate with pseudo-random bytes
    encrypted_data = os.urandom(60)  # 64 bytes total with prefix
    
    return payload + encrypted_data


def send_post(url: str, cookie_value: str, verbose: bool = True) -> bool:
    """Send the crafted POST request to the specified URL with the given cookie.

    Args:
        url: Full URL including query string (e.g., http://host/submit.php?id=12345).
        cookie_value: Base64-encoded string to set as the sessionid value.
        verbose: Whether to print detailed output.
        
    Returns:
        True if request was successful, False otherwise.
    """
    # Cobalt Strike beacons use specific User-Agent strings
    # Common ones include IE, Chrome, or custom values
    headers = {
        'Content-Type': 'application/octet-stream',
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.93 Safari/537.36',
        'Cookie': f'sessionid={cookie_value}'
    }
    payload = build_payload()
    
    try:
        if verbose:
            print(f"Sending POST to {url}")
            print(f"Headers: {headers}")
            print(f"Payload length: {len(payload)} bytes")
            print(f"Cookie length: {len(cookie_value)} characters")
        else:
            timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            print(f"[{timestamp}] Beacon check-in -> {url}")
            
        response = requests.post(url, headers=headers, data=payload, timeout=10)
        
        if verbose:
            print(f"Response status: {response.status_code}")
            if response.content:
                try:
                    print(f"Response body: {response.text}")
                except UnicodeDecodeError:
                    # If the response is not text, print raw bytes
                    print(f"Response body (raw bytes): {response.content}")
        else:
            print(f"[{timestamp}] Response: {response.status_code} ({len(response.content)} bytes)")
            
        return True
    except requests.exceptions.RequestException as e:
        print(f"[ERROR] Request failed: {e}")
        return False


def main() -> None:
    parser = argparse.ArgumentParser(description="Send a Cobalt Strike-like POST request.")
    parser.add_argument('--domain', default='localhost:8000',
                        help='Target domain/IP with optional port (default: localhost:8000)')
    parser.add_argument('--url', default='',
                        help='Full URL for the request (overrides --domain if provided)')
    parser.add_argument('--id', type=int, default=12345,
                        help='Numeric id to append as a query parameter (default: 12345)')
    parser.add_argument('--cookie-value', default='',
                        help='Base64-encoded cookie value (defaults to randomly generated)')
    parser.add_argument('--beacon-mode', action='store_true',
                        help='Enable continuous beacon mode (periodic check-ins)')
    parser.add_argument('--duration', type=float, default=5.0,
                        help='Duration to run in beacon mode (minutes, default: 5)')
    parser.add_argument('--interval', type=float, default=60.0,
                        help='Sleep interval between beacons (seconds, default: 60)')
    parser.add_argument('--jitter', type=float, default=0.0,
                        help='Jitter percentage for interval randomization (0-100, default: 0)')
    args = parser.parse_args()

    # Build the full URL: use --url if provided, otherwise construct from --domain
    if args.url:
        url = args.url
    else:
        url = f"http://{args.domain}/submit.php"
    
    # Append the id parameter if not already present
    if 'id=' not in url:
        # Append id as a query parameter
        delimiter = '&' if '?' in url else '?'
        url = f"{url}{delimiter}id={args.id}"

    # Determine cookie value: use provided value or generate realistic one
    cookie_value = args.cookie_value
    if not cookie_value:
        # Generate a realistic Base64 cookie (128+ characters)
        # Real Cobalt Strike cookies are long Base64-encoded encrypted session data
        random_bytes = os.urandom(96)  # 96 bytes -> 128 chars in Base64
        cookie_value = base64.b64encode(random_bytes).decode('ascii')
    
    if args.beacon_mode:
        # Beacon mode: continuous periodic check-ins
        print("=" * 60)
        print("BEACON MODE ACTIVATED")
        print("=" * 60)
        print(f"Target URL: {url}")
        print(f"Duration: {args.duration} minutes")
        print(f"Interval: {args.interval} seconds")
        print(f"Jitter: {args.jitter}%")
        print(f"Cookie length: {len(cookie_value)} characters")
        print("=" * 60)
        
        end_time = datetime.now() + timedelta(minutes=args.duration)
        beacon_count = 0
        
        try:
            while datetime.now() < end_time:
                beacon_count += 1
                print(f"\n--- Beacon #{beacon_count} ---")
                
                success = send_post(url, cookie_value, verbose=False)
                
                # Calculate sleep time with jitter
                sleep_time = args.interval
                if args.jitter > 0:
                    jitter_range = args.interval * (args.jitter / 100.0)
                    jitter_value = (os.urandom(1)[0] / 255.0) * jitter_range * 2 - jitter_range
                    sleep_time = max(1, args.interval + jitter_value)
                
                remaining = (end_time - datetime.now()).total_seconds()
                if remaining <= 0:
                    break
                    
                actual_sleep = min(sleep_time, remaining)
                next_beacon = datetime.now() + timedelta(seconds=actual_sleep)
                print(f"Sleeping {actual_sleep:.1f}s until next beacon (@ {next_beacon.strftime('%H:%M:%S')})")
                time.sleep(actual_sleep)
                
        except KeyboardInterrupt:
            print("\n\n[!] Beacon mode interrupted by user")
        
        print("\n" + "=" * 60)
        print(f"Beacon mode completed. Total beacons sent: {beacon_count}")
        print("=" * 60)
    else:
        # Single request mode
        print(f"Generated cookie value ({len(cookie_value)} chars): {cookie_value[:40]}...")
        send_post(url, cookie_value, verbose=True)


if __name__ == '__main__':
    main()