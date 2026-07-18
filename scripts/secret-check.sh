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
    "PASSWORD=[^t]"
    "_PASSWORD=[^t]"
    "PASSWD="
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
    # Pomiń plik z definicjami patternów
    if [[ "$file" == *"secret-check.sh"* ]]; then
        continue
    fi

    # Pomiń pliki binarne
    if ! file "$file" | grep -q "text"; then
        continue
    fi

    # Sprawdź zawartość staged pliku
    CONTENT=$(git show :"$file" 2>/dev/null)

    for pattern in "${SECRETS_PATTERNS[@]}"; do
        MATCHING_LINES=$(echo "$CONTENT" | grep -n "$pattern" | grep -v "^#" | grep -v "//" | head -3)
        
        if [ -n "$MATCHING_LINES" ]; then
            # Sprawdź czy matching lines zawierają placeholder
            for line in $MATCHING_LINES; do
                IS_PLACEHOLDER=false
                for allowed in "${ALLOWED_PLACEHOLDERS[@]}"; do
                    if echo "$line" | grep -q "$allowed"; then
                        IS_PLACEHOLDER=true
                        break
                    fi
                done

                if [ "$IS_PLACEHOLDER" = false ]; then
                    ERRORS="$ERRORS\n⚠️  Potencjalny sekret w pliku: $file (pattern: $pattern)"
                    ERRORS="$ERRORS\n   Linia: $line"
                    break
                fi
            done
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
