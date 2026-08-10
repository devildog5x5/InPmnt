"""Create a local self-signed certificate for HTTPS development."""
from __future__ import annotations

import argparse
import ipaddress
import sys
from datetime import datetime, timedelta, timezone
from pathlib import Path


def cert_paths(cert_dir: Path | None = None) -> tuple[Path, Path]:
    root = Path(__file__).resolve().parent.parent
    cert_dir = Path(cert_dir) if cert_dir else root / "certs"
    cert_dir.mkdir(parents=True, exist_ok=True)
    return cert_dir / "localhost.pem", cert_dir / "localhost-key.pem"


def ensure_local_certs(
    cert_dir: Path | None = None, *, force: bool = False
) -> tuple[Path, Path]:
    """Return (cert_pem, key_pem), generating them if missing (or if force=True)."""
    cert_path, key_path = cert_paths(cert_dir)
    if not force and cert_path.is_file() and key_path.is_file():
        return cert_path, key_path

    from cryptography import x509
    from cryptography.hazmat.primitives import hashes, serialization
    from cryptography.hazmat.primitives.asymmetric import rsa
    from cryptography.x509.oid import ExtendedKeyUsageOID, NameOID

    key = rsa.generate_private_key(public_exponent=65537, key_size=2048)
    subject = issuer = x509.Name(
        [
            x509.NameAttribute(NameOID.COUNTRY_NAME, "US"),
            x509.NameAttribute(NameOID.ORGANIZATION_NAME, "InPmnt Local"),
            x509.NameAttribute(NameOID.COMMON_NAME, "localhost"),
        ]
    )
    now = datetime.now(timezone.utc)
    cert = (
        x509.CertificateBuilder()
        .subject_name(subject)
        .issuer_name(issuer)
        .public_key(key.public_key())
        .serial_number(x509.random_serial_number())
        .not_valid_before(now - timedelta(minutes=5))
        .not_valid_after(now + timedelta(days=825))
        .add_extension(
            x509.SubjectAlternativeName(
                [
                    x509.DNSName("localhost"),
                    x509.IPAddress(ipaddress.IPv4Address("127.0.0.1")),
                ]
            ),
            critical=False,
        )
        .add_extension(
            x509.ExtendedKeyUsage([ExtendedKeyUsageOID.SERVER_AUTH]),
            critical=False,
        )
        .add_extension(
            x509.BasicConstraints(ca=False, path_length=None),
            critical=True,
        )
        .sign(key, hashes.SHA256())
    )

    key_path.write_bytes(
        key.private_bytes(
            encoding=serialization.Encoding.PEM,
            format=serialization.PrivateFormat.TraditionalOpenSSL,
            encryption_algorithm=serialization.NoEncryption(),
        )
    )
    cert_path.write_bytes(cert.public_bytes(serialization.Encoding.PEM))
    return cert_path, key_path


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="Generate or replace the local InPmnt HTTPS certificate."
    )
    parser.add_argument(
        "--force",
        action="store_true",
        help="Overwrite certs/localhost.pem and certs/localhost-key.pem",
    )
    args = parser.parse_args(argv)
    cert_path, key_path = ensure_local_certs(force=args.force)
    action = "Replaced" if args.force else "Ready"
    print(f"{action}: {cert_path}")
    print(f"{action}: {key_path}")
    print("Restart the app (.\\start.ps1) for changes to take effect.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
