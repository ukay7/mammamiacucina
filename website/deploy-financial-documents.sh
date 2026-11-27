#!/usr/bin/env bash
# Deploy the invoice and order-payment receipt release, or switch its layout.
set -euo pipefail

usage() {
    echo 'Usage: deploy-financial-documents.sh deploy <release-commit> | original | revised | status' >&2
    exit 2
}

action=${1:-}
case "$action" in
    deploy) [[ $# -eq 2 && ${2:-} =~ ^[0-9a-f]{40}$ ]] || usage ;;
    original|revised|status) [[ $# -eq 1 ]] || usage ;;
    *) usage ;;
esac

[[ $(id -u) == 0 ]] || { echo 'Run as root or with sudo.' >&2; exit 1; }
cd /var/www/mammamiacucina-app/website
test -f artisan
test -f .env

as_deploy() {
    sudo -H -u mmcdeploy "$@"
}

artisan() {
    as_deploy php8.4 artisan "$@"
}

case "$action" in
    original|revised) artisan documents:financial-layout "$action"; exit ;;
    status) artisan documents:financial-layout; exit ;;
esac

release=$2
current=$(as_deploy git rev-parse HEAD)
if [[ $(as_deploy git branch --show-current) != main ]] || [[ -n $(as_deploy git status --porcelain --untracked-files=no) ]]; then
    echo 'Deploy from the main branch with no local changes to tracked files.' >&2
    exit 1
fi
if ! as_deploy git merge-base --is-ancestor "$current" "$release" || ! as_deploy git merge-base --is-ancestor "$release" origin/main; then
    echo 'The checkout cannot fast-forward to the specified release on origin/main.' >&2
    exit 1
fi
if ! as_deploy git diff --quiet "$current" "$release" -- composer.json composer.lock; then
    echo 'Composer dependencies differ. Use the normal full deployment for this checkout.' >&2
    exit 1
fi
php8.4 -r 'if (!extension_loaded("gd")) { fwrite(STDERR, "PHP 8.4 GD is required for document PDFs.\n"); exit(1); }'
systemctl is-active --quiet php8.4-fpm

artisan down --retry=60
trap 'if (( $? != 0 )); then echo "Deployment stopped during maintenance. Resolve the reported error, then rerun the deploy command." >&2; fi' EXIT
as_deploy git merge --ff-only "$release"
artisan migrate --path=database/migrations/2026_11_27_120000_add_financial_document_layout.php --force
artisan view:cache
artisan documents:financial-layout revised
artisan queue:restart
systemctl reload php8.4-fpm
artisan up
trap - EXIT

curl --fail --silent --show-error --location --max-time 20 --output /dev/null https://mammamiacucina.ca/admin/login
as_deploy git log -1 --oneline
echo 'Revised invoices and payment receipts are active. The public login responded successfully.'
