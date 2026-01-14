#!/bin/bash
# Pre-commit check for sensitive data
# Run this before committing to ensure no secrets are exposed

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "🔍 Checking for sensitive data before commit..."
echo ""

ERRORS=0

# Patterns to search for (add your own patterns here)
PATTERNS=(
    "u514173348"           # Hostinger username pattern
    "DB_PASS=."            # Actual password set (not empty)
    "ADMIN_SECRET_CODE=(?!change_this|your_)"  # Real secret codes
    "password.*=.*['\"][^'\"]+['\"]"  # Hardcoded passwords
)

# Files to check (exclude .env which should be gitignored)
FILES_TO_CHECK=$(git diff --cached --name-only 2>/dev/null || find . -type f \( -name "*.php" -o -name "*.js" -o -name "*.sql" -o -name "*.md" -o -name "*.json" \) ! -path "./.git/*" ! -name ".env")

# Check if .env is staged (should NEVER be committed)
if git diff --cached --name-only 2>/dev/null | grep -q "^\.env$"; then
    echo -e "${RED}❌ CRITICAL: .env file is staged for commit!${NC}"
    echo "   Run: git reset HEAD .env"
    ERRORS=$((ERRORS + 1))
fi

# Check for Hostinger-specific patterns
echo "Checking for Hostinger credentials..."
HOSTINGER_MATCHES=$(grep -r "u514173348" --include="*.php" --include="*.js" --include="*.sql" --include="*.md" --include="*.json" . 2>/dev/null | grep -v ".git" | grep -v ".env")
if [ -n "$HOSTINGER_MATCHES" ]; then
    echo -e "${RED}❌ Found Hostinger username in files:${NC}"
    echo "$HOSTINGER_MATCHES"
    ERRORS=$((ERRORS + 1))
fi

# Check for email addresses (might be personal)
echo "Checking for email addresses..."
EMAIL_MATCHES=$(grep -rE "[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}" --include="*.php" --include="*.js" . 2>/dev/null | grep -v ".git" | grep -v "example.com" | grep -v "@param" | grep -v "@return")
if [ -n "$EMAIL_MATCHES" ]; then
    echo -e "${YELLOW}⚠️  Found email addresses (verify these are okay):${NC}"
    echo "$EMAIL_MATCHES"
fi

# Check for API keys patterns
echo "Checking for potential API keys..."
API_KEY_MATCHES=$(grep -rE "(api[_-]?key|apikey|secret[_-]?key)\s*[=:]\s*['\"][^'\"]{10,}['\"]" --include="*.php" --include="*.js" . 2>/dev/null | grep -v ".git" | grep -v ".env")
if [ -n "$API_KEY_MATCHES" ]; then
    echo -e "${RED}❌ Found potential API keys:${NC}"
    echo "$API_KEY_MATCHES"
    ERRORS=$((ERRORS + 1))
fi

# Check for private IPs or localhost with ports (might indicate dev config)
echo "Checking for localhost/private IPs..."
IP_MATCHES=$(grep -rE "localhost:[0-9]+|127\.0\.0\.1:[0-9]+|192\.168\.[0-9]+\.[0-9]+" --include="*.php" --include="*.js" . 2>/dev/null | grep -v ".git")
if [ -n "$IP_MATCHES" ]; then
    echo -e "${YELLOW}⚠️  Found localhost/private IPs (verify these are okay):${NC}"
    echo "$IP_MATCHES"
fi

echo ""
echo "========================================"
if [ $ERRORS -gt 0 ]; then
    echo -e "${RED}❌ Found $ERRORS security issue(s)!${NC}"
    echo "Please fix these before committing."
    exit 1
else
    echo -e "${GREEN}✅ No sensitive data detected!${NC}"
    echo "Safe to commit."
    exit 0
fi
