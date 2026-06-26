#!/usr/bin/env bash
# Generate RSA-4096 keypair for skopeo ocicrypt JWE layer encryption.
#
# After running:
#   pubkey.pem  → commit to repo (safe — public key only)
#   privkey.pem → store on worker VM + set as CI secret REGISTRY_PRIVKEY_PATH
#                 NEVER commit to git

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PRIVKEY="$SCRIPT_DIR/privkey.pem"
PUBKEY="$SCRIPT_DIR/pubkey.pem"

if [ -f "$PRIVKEY" ]; then
    echo "❌  privkey.pem already exists — delete it first if you want to rotate keys."
    exit 1
fi

echo "Generating RSA-4096 private key..."
openssl genrsa -out "$PRIVKEY" 4096

echo "Extracting public key..."
openssl rsa -in "$PRIVKEY" -pubout -out "$PUBKEY"

chmod 600 "$PRIVKEY"
chmod 644 "$PUBKEY"

echo ""
echo "✅  Keys generated:"
echo "    Public key  (commit this): $PUBKEY"
echo "    Private key (DO NOT commit): $PRIVKEY"
echo ""
echo "Next steps:"
echo "  1. git add yads-infra/registry-php/pubkey.pem && git commit -m 'chore: add registry pubkey'"
echo "  2. Copy privkey.pem to the worker VM: scp privkey.pem root@raspi4:~/.yads/registry_privkey.pem"
echo "  3. Set REGISTRY_PRIVKEY_PATH=~/.yads/registry_privkey.pem in your environment / CI secrets"
echo "  4. rm privkey.pem   (remove local copy)"
