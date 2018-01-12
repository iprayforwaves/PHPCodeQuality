#!/usr/bin/env bash

ROOT="$PWD/../.."

echo "Checking Code Style..."

PHP_CS_FIXER="bin/php-cs-fixer"
HAS_PHP_CS_FIXER=false

if [ -x bin/php-cs-fixer ]; then
    HAS_PHP_CS_FIXER=true
fi

if $HAS_PHP_CS_FIXER; then
    git status --porcelain | grep -e '^[AM]\(.*\).php$' | cut -c 3- | while read line; do
        $PHP_CS_FIXER fix --rules=@PSR2 "$line";
        git add "$line";
    done
else
    echo ""
    echo "Please install php-cs-fixer, e.g.:"
    echo ""
    echo "  composer require --dev friendsofphp/php-cs-fixer:dev-master"
    echo ""
fi

#/usr/bin/env php $PWD/.git/hooks/PreCommit.php