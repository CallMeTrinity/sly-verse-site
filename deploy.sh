#!/bin/bash
set -e

# Lancé sur le serveur Infomaniak par .github/workflows/deploy.yml,
# APRÈS que le runner ait rsync vendor/, web/dist/ et les sources.
#
# Le serveur n'a pas Node : le build Vite est fait sur le runner GitHub.
# Ici on ne fait que la partie Craft/PHP.

cd "$(dirname "$0")"

php craft up --interactive=0
php craft clear-caches/all --interactive=0

echo "Deploy terminé."
