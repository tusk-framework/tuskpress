#!/usr/bin/env bash
set -Eeuo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
run_id="$(date -u +%Y%m%d%H%M%S)-$$-${RANDOM}"
project="tuskpress-smoke-${run_id}"
project_pattern='^tuskpress-smoke-[0-9]{14}-[0-9]+-[0-9]+$'
compose=(docker compose --project-name "$project" --file "$repo_root/docker-compose.yml" --env-file "$repo_root/.env.example")
logs_dir="${TUSKPRESS_SMOKE_LOG_DIR:-${TMPDIR:-/tmp}/tuskpress-smoke-logs}"
mkdir -p "$logs_dir"
logs_file="${logs_dir}/${project}.logs"
cookie_file="${TMPDIR:-/tmp}/${project}.cookies"
cleanup_armed=0

compose_project() {
    local config_json configured
    config_json="$("${compose[@]}" config --format json)" || return 1
    configured="$(php -r '$config = json_decode(stream_get_contents(STDIN), true); if (!is_array($config) || !isset($config["name"]) || !is_string($config["name"])) { exit(1); } echo $config["name"];' <<< "$config_json")" || {
        printf 'Refusing Compose operation: project identity validation failed (%s / invalid JSON name)\n' "$project" >&2
        return 1
    }
    [[ "$configured" == "$project" && "$project" =~ $project_pattern ]] || {
        printf 'Refusing Compose operation: project identity validation failed (%s / %s)\n' "$project" "$configured" >&2
        return 1
    }
}

cleanup() {
    local exit_code=$?
    trap - EXIT
    if (( cleanup_armed )); then
        if ! compose_project; then
            printf 'Cleanup refused; preserving logs at %s\n' "$logs_file" >&2
            exit 1
        fi
        if (( exit_code != 0 )); then
            "${compose[@]}" logs --no-color >"$logs_file" 2>&1 || true
            printf 'Smoke failed; logs preserved at %s\n' "$logs_file" >&2
            cat "$logs_file" >&2
        fi
        "${compose[@]}" down --volumes --remove-orphans || exit_code=1
    fi
    rm -f "$cookie_file"
    exit "$exit_code"
}
trap cleanup EXIT

compose_project
cleanup_armed=1

if curl --silent --show-error --fail http://127.0.0.1:8080/healthz >/dev/null 2>&1; then
    printf 'Refusing smoke test: host port 8080 already serves an app.\n' >&2
    exit 1
fi

"${compose[@]}" up --detach --build --wait --wait-timeout 180

wait_for_http() {
    local url="$1" attempt
    for attempt in $(seq 1 90); do
        if curl --silent --show-error --fail "$url" >/dev/null; then return 0; fi
        sleep 2
    done
    printf 'Timed out waiting for %s\n' "$url" >&2
    return 1
}

wait_for_http http://127.0.0.1:8080/healthz
[[ "$(curl --silent --show-error --fail http://127.0.0.1:8080/healthz)" == "ok" ]]

installer="$(curl --silent --show-error --fail http://127.0.0.1:8080/wp-admin/install.php)"
[[ "$installer" == *"TuskPress Installation"* ]]

"${compose[@]}" exec -T web php < "$repo_root/tests/Smoke/mysql-install-lock.php"

admin_user="smoke-${run_id}"
admin_password="Smoke-${run_id}-Pass!"
admin_email="${admin_user}@example.test"
install_status="$(curl --silent --show-error --output /dev/null --cookie-jar "$cookie_file" \
    --data-urlencode 'site_title=TuskPress Smoke' \
    --data-urlencode "admin_user=${admin_user}" \
    --data-urlencode "admin_pass=${admin_password}" \
    --data-urlencode "admin_email=${admin_email}" \
    http://127.0.0.1:8080/wp-admin/install.php \
    --write-out '%{http_code}')"
[[ "$install_status" == "302" ]]

repeat_install_status="$(curl --silent --show-error --output /dev/null \
    --data-urlencode 'site_title=Must Not Replace Site' \
    --data-urlencode 'admin_user=must-not-exist' \
    --data-urlencode 'admin_pass=must-not-work' \
    --data-urlencode 'admin_email=attacker@example.test' \
    http://127.0.0.1:8080/wp-admin/install.php --write-out '%{http_code}')"
[[ "$repeat_install_status" == "409" ]]

login_result="$(curl --silent --show-error --output /dev/null --cookie "$cookie_file" --cookie-jar "$cookie_file" \
    --data-urlencode "log=${admin_user}" \
    --data-urlencode "pwd=${admin_password}" \
    --data-urlencode 'redirect_to=wp-admin' \
    http://127.0.0.1:8080/wp-login \
    --write-out '%{http_code}|%{redirect_url}')"
IFS='|' read -r login_status login_location <<< "$login_result"
[[ "$login_status" == "302" ]]
[[ "$login_location" == "http://127.0.0.1:8080/wp-admin" ]]

admin_response="$(curl --silent --show-error --fail --cookie "$cookie_file" http://127.0.0.1:8080/wp-admin)"
[[ "$admin_response" == *"class=\"user-role\">Administrator"* ]]
[[ "$admin_response" == *"TuskPress Smoke"* ]]
anonymous_result="$(curl --silent --show-error --output /dev/null http://127.0.0.1:8080/wp-admin --write-out '%{http_code}|%{redirect_url}')"
[[ "$anonymous_result" == "302|http://127.0.0.1:8080/wp-login" ]]
for render in 1 2; do
    frontend="$(curl --silent --show-error --fail http://127.0.0.1:8080/)"
    [[ "$frontend" == *'<header class="navbar">'* && "$frontend" == *'<footer class="site-footer">'* ]]
done
printf 'Smoke passed: healthz, concurrent-install lock, first installation, repeat POST rejection, login, authenticated/anonymous isolation, and repeated theme renders.\n'
