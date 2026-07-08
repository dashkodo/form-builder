#!/bin/sh
set -eu

apache2-foreground &

if [ "${IT_SUBDOMAIN:-}" ] && [ "${IT_API_KEY:-}" ]; then
	it --kill "$IT_SUBDOMAIN" --api-key "$IT_API_KEY" || true
	exec it 80 -s "$IT_SUBDOMAIN" --api-key "$IT_API_KEY"
fi

if [ "${IT_SUBDOMAIN:-}" ] || [ "${IT_API_KEY:-}" ]; then
	echo "IT_SUBDOMAIN and IT_API_KEY must both be set" >&2
	exit 1
fi

exec it 80