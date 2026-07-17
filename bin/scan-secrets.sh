#!/usr/bin/env bash
set -euo pipefail

RED='\033[0;31m'
YELLOW='\033[1;33m'
GREEN='\033[0;32m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
BOLD='\033[1m'
NC='\033[0m'

SCAN_DIR="${1:-.}"
FINDINGS=0
FILES_SCANNED=0
HIGH_COUNT=0
MEDIUM_COUNT=0
LOW_COUNT=0

declare -A PATTERNS=(
    ["AWS Access Key|HIGH"]="AKIA[0-9A-Z]{16}"
    ["AWS Secret Key|HIGH"]="(?i)aws_secret_access_key\s*[=:]\s*[A-Za-z0-9/+=]{40}"
    ["GitHub Token|HIGH"]="gh[pousr]_[0-9a-zA-Z]{36,255}"
    ["GitHub PAT|HIGH"]="github_pat_[A-Za-z0-9_]{22,}"
    ["Google API Key|HIGH"]="AIza[0-9A-Za-z_-]{35}"
    ["Slack Token|HIGH"]="xox[baprs]-[0-9a-zA-Z]{10,}"
    ["Stripe Key|HIGH"]="[sr]k_live_[0-9a-zA-Z]{24,}"
    ["Private Key|HIGH"]="-----BEGIN (RSA |EC |DSA |OPENSSH )?PRIVATE KEY-----"
    ["JWT Token|MEDIUM"]="eyJ[A-Za-z0-9_-]{10,}\.eyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]+"
    ["Password in URL|HIGH"]="(?i)(ftp|http|https)://[^:\${}<>]+:[^@\${}]+@[^\${}]+"
    ["Hardcoded Password|MEDIUM"]="(?i)(password|passwd|pwd)\s*[=:]\s*['\"][^'\"]{8,}['\"]"
    ["Hardcoded Secret|MEDIUM"]="(?i)(secret|api_key|apikey|access_key)\s*[=:]\s*['\"][^'\"]{8,}['\"]"
    ["Hardcoded Token|MEDIUM"]="(?i)token\s*[=:]\s*['\"][^'\"]{8,}['\"]"
    ["Supabase Service Role|MEDIUM"]="(?i)SUPABASE_SERVICE_ROLE_KEY\s*=\s*ey[A-Za-z0-9._-]+"
    ["Database Password|HIGH"]="(?i)(POSTGRES_PASSWORD|MYSQL_PASSWORD|DB_PASSWORD)\s*=\s*\S+"
    ["Bearer Token|MEDIUM"]="(?i)authorization\s*:\s*bearer\s+[A-Za-z0-9_-]{20,}"
    ["Base64 Encoded Secret|LOW"]="(?i)(secret|password|key|token)\s*[=:]\s*[A-Za-z0-9+/]{60,}={0,2}"
)

declare -a SKIP_EXTENSIONS=(
    ".lock" ".min.js" ".min.css" ".map" ".woff" ".woff2" ".ttf" ".eot"
    ".png" ".jpg" ".jpeg" ".gif" ".svg" ".ico" ".bmp" ".webp"
    ".pdf" ".zip" ".tar" ".gz" ".bz2" ".rar" ".7z"
    ".exe" ".dll" ".so" ".dylib" ".bin" ".class" ".jar" ".war"
    ".pyc" ".pyo" ".o" ".obj" ".wasm"
)

is_false_positive() {
    local line="$1"
    local pattern_name="$2"

    [[ "$line" == *'%env('*')%'* ]] && return 0
    [[ "$line" == *'${'*'}'* ]] && return 0
    [[ "$line" == *'changeme'* ]] && return 0
    [[ "$line" == *'example'* ]] && return 0
    [[ "$line" == *'EXAMPLE'* ]] && return 0
    [[ "$line" == *'your_'* ]] && return 0
    [[ "$line" == *'placeholder'* ]] && return 0
    [[ "$line" == *'<your'* ]] && return 0
    [[ "$line" == *'********'* ]] && return 0
    [[ "$line" == *'***MASKED***'* ]] && return 0
    [[ "$line" == *'xxx'* ]] && return 0
    [[ "$line" == *'XXX'* ]] && return 0
    [[ "$line" == *'test-secret'* ]] && return 0
    [[ "$line" == *'test-key'* ]] && return 0
    [[ "$line" == *'test-gemini-key'* ]] && return 0
    [[ "$line" == *'my-secret-key'* ]] && return 0
    [[ "$line" == *'$2y$'* ]] && return 0
    [[ "$line" == *'$2a$'* ]] && return 0
    [[ "$line" == *'$2b$'* ]] && return 0
    [[ "$line" == *'fonts.googleapis.com'* ]] && return 0
    [[ "$line" == *'gstatic.com'* ]] && return 0
    [[ "$line" == *'cdn.'* ]] && return 0
    [[ "$line" == *'localhost'* ]] && return 0
    [[ "$line" == *'127.0.0.1'* ]] && return 0

    if [[ "$pattern_name" == *"Password in URL"* ]]; then
        [[ "$line" =~ https?://[^:]+:[^@]+@ ]] || return 0
    fi

    return 1
}

should_skip_file() {
    local file="$1"
    for ext in "${SKIP_EXTENSIONS[@]}"; do
        [[ "$file" == *"$ext" ]] && return 0
    done
    return 1
}

severity_color() {
    case "$1" in
        HIGH)   echo "$RED" ;;
        MEDIUM) echo "$YELLOW" ;;
        LOW)    echo "$CYAN" ;;
        *)      echo "$NC" ;;
    esac
}

severity_badge() {
    local sev="$1"
    local color
    color="$(severity_color "$sev")"
    printf "%s[%s]%s" "$color" "$sev" "$NC"
}

echo ""
echo -e "${BOLD}╔══════════════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║     🔍  Sensitive Data Leak Scanner (git-tracked)       ║${NC}"
echo -e "${BOLD}╚══════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${CYAN}Scan directory:${NC}  $(realpath "$SCAN_DIR")"
echo -e "${CYAN}Patterns:${NC}        ${#PATTERNS[@]} rules (HIGH / MEDIUM / LOW)"
echo ""

mapfile -t TRACKED_FILES < <(git -C "$SCAN_DIR" ls-files 2>/dev/null || find "$SCAN_DIR" -type f -not -path '*/.git/*')

TOTAL=${#TRACKED_FILES[@]}
echo -e "${BOLD}Found ${TOTAL} git-tracked files to scan.${NC}"
echo -e "${CYAN}──────────────────────────────────────────────────────────${NC}"
echo ""

declare -A SEEN_FINDINGS

for file in "${TRACKED_FILES[@]}"; do
    full_path="$SCAN_DIR/$file"

    [[ -f "$full_path" ]] || continue
    should_skip_file "$full_path" && continue

    ((FILES_SCANNED++)) || true

    for entry in "${!PATTERNS[@]}"; do
        pattern_name="${entry%%|*}"
        severity="${entry##*|}"
        pattern="${PATTERNS[$entry]}"

        while IFS=: read -r line_num line_content; do
            [[ -z "$line_num" ]] && continue
            is_false_positive "$line_content" "$pattern_name" && continue

            finding_key="${file}:${line_num}:${pattern_name}"
            [[ -n "${SEEN_FINDINGS[$finding_key]+x}" ]] && continue
            SEEN_FINDINGS["$finding_key"]=1

            ((FINDINGS++)) || true
            case "$severity" in
                HIGH)   ((HIGH_COUNT++)) || true ;;
                MEDIUM) ((MEDIUM_COUNT++)) || true ;;
                LOW)    ((LOW_COUNT++)) || true ;;
            esac

            badge="$(severity_badge "$severity")"
            echo -e "  ${badge} ${BOLD}${pattern_name}${NC}"
            echo -e "    ${YELLOW}File:${NC}   ${file}:${line_num}"
            trimmed="$(echo "$line_content" | sed 's/^[[:space:]]*//' | head -c 150)"
            echo -e "    ${YELLOW}Line:${NC}   ${trimmed}"
            echo ""
        done < <(grep -nP "$pattern" "$full_path" 2>/dev/null || true)
    done
done

echo -e "${CYAN}──────────────────────────────────────────────────────────${NC}"
echo ""
echo -e "${BOLD}Summary:${NC}"
echo -e "  Files scanned:  ${FILES_SCANNED} / ${TOTAL}"
echo ""
if [[ $FINDINGS -eq 0 ]]; then
    echo -e "  Findings:       ${GREEN}${BOLD}0 — no sensitive data leaks detected${NC}"
else
    echo -e "  Total findings: ${RED}${BOLD}${FINDINGS}${NC}"
    echo -e "    ${RED}HIGH:${NC}    ${HIGH_COUNT}"
    echo -e "    ${YELLOW}MEDIUM:${NC}  ${MEDIUM_COUNT}"
    echo -e "    ${CYAN}LOW:${NC}     ${LOW_COUNT}"
fi
echo ""

if [[ $HIGH_COUNT -gt 0 ]]; then
    echo -e "  ${RED}${BOLD}⚠  HIGH severity findings require immediate attention!${NC}"
    echo ""
fi

exit $(( FINDINGS > 0 ? 1 : 0 ))
