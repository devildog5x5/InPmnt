"""Factory-reset the InPmnt SQLite database (users, passwords, and all app data)."""
from __future__ import annotations

import argparse
import os
from pathlib import Path

from dotenv import load_dotenv

from .database import reset_database


def main(argv: list[str] | None = None) -> int:
    root = Path(__file__).resolve().parent.parent
    load_dotenv(root / ".env")
    parser = argparse.ArgumentParser(
        description="Wipe InPmnt SQLite data and re-seed the default admin and demo accounts."
    )
    parser.add_argument("--yes", "-y", action="store_true", help="Skip the confirmation prompt")
    parser.add_argument(
        "--db",
        default="",
        help="SQLite path (default: DATABASE_PATH or ./inpmnt.db)",
    )
    args = parser.parse_args(argv)
    db_path = (args.db or os.environ.get("DATABASE_PATH") or "").strip() or str(root / "inpmnt.db")
    print("This will DELETE all users, passwords, invoices, clients, and reminders in:")
    print(f"  {db_path}")
    print("Default admin and demo accounts will be recreated.")
    if not args.yes:
        answer = input("Type RESET to continue: ").strip()
        if answer != "RESET":
            print("Cancelled.")
            return 1
    reset_database(db_path)
    print("Database cleared. Sign in as admin@inpmnt.app with the initial password.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
