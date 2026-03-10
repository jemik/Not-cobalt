"""
Simple HTTP server that mimics a Cobalt Strike submit.php endpoint.

This script spins up an HTTP server listening on a configurable port (default 8000).
It responds to POST requests directed at `/submit.php` with a 200 OK status and
prints basic information about the request to the console.  All other paths
return 404 Not Found.

Run this script in a terminal before executing the client script.
"""

import argparse
import http.server
import socketserver


class SubmitHandler(http.server.BaseHTTPRequestHandler):
    """Custom request handler that accepts POSTs to /submit.php."""

    def log_message(self, format: str, *args) -> None:
        """Override to silence the default HTTP server logging."""
        return

    def do_POST(self) -> None:
        """Handle POST requests to /submit.php."""
        if self.path.startswith("/submit.php"):
            content_length = int(self.headers.get('Content-Length', 0))
            body = self.rfile.read(content_length) if content_length > 0 else b''
            print(f"Received POST to {self.path}")
            print(f"Headers:\n{self.headers}")
            preview_bytes = body[:8]
            preview_hex = ' '.join(f'{b:02x}' for b in preview_bytes)
            print(f"Body preview (first {len(preview_bytes)} bytes): {preview_hex}")
            self.send_response(200)
            self.send_header('Content-Type', 'text/plain')
            self.end_headers()
            self.wfile.write(b'OK')
        else:
            self.send_response(404)
            self.end_headers()
            self.wfile.write(b'Not Found')


def main() -> None:
    parser = argparse.ArgumentParser(description="Run a simple HTTP server for submit.php testing.")
    parser.add_argument('--port', type=int, default=8000, help='Port to listen on (default: 8000)')
    args = parser.parse_args()
    with socketserver.TCPServer(('0.0.0.0', args.port), SubmitHandler) as httpd:
        print(f"Fake submit.php server listening on port {args.port}")
        try:
            httpd.serve_forever()
        except KeyboardInterrupt:
            print("\nShutting down server")


if __name__ == '__main__':
    main()