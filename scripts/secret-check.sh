#!/bin/bash
# Pre-commit hook: sprawdza czy nie commitujesz sekretów

SECRETS_PATTERNS=(
    "sb_secret_"
    "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9"
    "pplx-"
    "sk-proj-"
    "ghp_"
    "gho_"
    "glpat-"
    "xox[baprs]-"
)

ALLOWED_PLACEHOLDERS=(
    "test-key"
    "test-secret"
    "your_"
    "changeme"
    "placeholder"
)

# Sprawdź tylko pliki staged
STAGED_FILES=$(git diff --cached --name-only --diff-filter=ACM)

if [ -z "$STAGED_FILES" ]; then
    exit 0
fi

ERRORS=""

for file in $STAGED_FILES; do
    # Pomiń pliki binarne
    if ! file "$file" | grep -q "text"; then
        continue
    fi

    # Sprawdź zawartość staged pliku
    CONTENT=$(git show :"$file" 2>/dev/null)

    for pattern in "${SECRETS_PATTERNS[@]}"; do
        if echo "$CONTENT" | grep -q "$pattern"; then
            # Sprawdź czy to nie placeholder
            IS_PLACEHOLDER=false
            for allowed in "${ALLOWED_PLACEHOLDERS[@]}"; do
                if echo "$CONTENT" | grep -q "$allowed"; then
                    IS_PLACEHOLDER=true
                    break
                fi
            done

            if [ "$IS_PLACEHOLDER" = false ]; then
                # Dodatkowa weryfikacja - sprawdź czy to faktycznie sekret
                # (nie tylko wzmianka w komentarzu)
                MATCHING_LINES=$(echo "$CONTENT" | grep -n "$pattern" | grep -v "^#" | grep -v "//" | head -3)
                if [ -n "$MATCHING_LINES" ]; then
                    ERRORS="$ERRORS\n⚠️  Potencjalny sekret w pliku: $file (pattern: $pattern)"
                    ERRORS="$ERRORS\n   Linia: $(echo "$MATCHING_LINES" | head -1)"
                fi
            fi
        fi
    done
done

if [ -n "$ERRORS" ]; then
    echo "🔒 Wykryto potencjalne sekrety w commitowanych plikach!"
    echo -e "$ERRORS"
    echo ""
    echo "Jeśli to fałszywy alarm, użyj: git commit --no-verify"
    exit 1
fi

echo "✅ Nie wykryto sekretów"
exit 0
