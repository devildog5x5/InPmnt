"""WSGI entry for cPanel / Hostinger-style Python App (Phusion Passenger).

FTP or File Manager the project into the Python application root (not public_html).
In Setup Python App set:

  Startup file: passenger_wsgi.py
  Entry point:  application
"""
from __future__ import annotations

import os
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent
os.chdir(ROOT)
if str(ROOT) not in sys.path:
    sys.path.insert(0, str(ROOT))

# Passenger / the host reverse-proxy terminates TLS. Flask serves HTTP inside.
os.environ.setdefault("USE_HTTPS", "0")

from app import create_app

application = create_app()
