"""Gunicorn entry for Ubuntu: gunicorn -b 127.0.0.1:5065 -w 2 wsgi:app"""
from __future__ import annotations

from pathlib import Path

from dotenv import load_dotenv

load_dotenv(Path(__file__).resolve().parent / ".env")
load_dotenv()

from web import create_app

app = create_app()
