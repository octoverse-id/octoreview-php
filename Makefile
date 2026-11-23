.DEFAULT_GOAL := help
SHELL := /bin/bash

# `make check` is the fast gate: PHP and Composer only (no Docker, no network once dependencies are
# installed). `make release-check` is the full gate. CI runs the same targets.

# PHP_CS_FIXER_IGNORE_ENV lets php-cs-fixer run on a PHP newer than it declares support for.
export PHP_CS_FIXER_IGNORE_ENV := 1

help: ## List available targets
	@grep -E '^[a-zA-Z0-9_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  %-20s %s\n", $$1, $$2}'

vendor/autoload.php: composer.json
	@composer install --no-interaction --no-progress --quiet

install: vendor/autoload.php ## Install the Composer dependencies

validate: ## Validate composer.json
	@composer validate --strict --quiet && echo "composer.json OK"

cs: vendor/autoload.php ## Check the coding style (php-cs-fixer, PER-CS 2.0)
	@vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: vendor/autoload.php ## Apply the coding style
	@vendor/bin/php-cs-fixer fix

stan: vendor/autoload.php ## Static analysis (PHPStan level 8)
	@vendor/bin/phpstan analyse --no-progress --memory-limit=512M

test: vendor/autoload.php ## Run the unit tests (offline)
	@vendor/bin/phpunit --exclude-group contract

contract: vendor/autoload.php ## Run the offline contract gate against testdata/contract (server docs/spec.md section 18)
	@vendor/bin/phpunit --group contract

vendor-contract: ## Refresh testdata/contract from the server (SERVER=<ref or checkout path>, default main; network)
	@./scripts/vendor-contract.sh $(or $(SERVER),main)

# The published sha-<commit> image of the server's main branch that CI's integration job runs
# against (docs/versioning.md); the weekly contract-drift workflow runs against :edge.
SERVER_IMAGE ?= ghcr.io/octoverse-id/octoreview:sha-bfa29155061e89a8f6c07496fa8b820b9e3c7440

integration: vendor/autoload.php ## Boot SERVER_IMAGE and run the quickstart and the integration suite (needs Docker)
	@./scripts/integration.sh $(SERVER_IMAGE)

fixtures: vendor/autoload.php ## Like integration, and refresh testdata/fixtures from the real server (needs Docker)
	@WRITE_FIXTURES=1 ./scripts/integration.sh $(SERVER_IMAGE)

llms-check: ## Fail when llms.txt links a missing local path (and prove the check can fail)
	@./scripts/check-llms-txt.sh
	@./scripts/check-llms-txt-test.sh

actionlint: ## Lint GitHub Actions workflows (needs actionlint on PATH)
	@actionlint -color

placeholder-check: ## Fail while any bootstrap CI placeholder job remains
	@if grep -rn 'octoreview:placeholder' .github/workflows; then \
		echo "placeholder-check: bootstrap placeholder jobs remain; replace them before a release" >&2; exit 1; \
	fi; echo "placeholder-check OK"

check: validate cs stan test contract llms-check ## Fast gate (PHP and Composer only)

release-check: check integration placeholder-check ## Full pre-release gate (needs Docker; fails while placeholders remain)

export-rulesets: ## Export the live rulesets into .github/rulesets/ (needs gh with repo admin)
	@./scripts/export-rulesets.sh

.PHONY: help install validate cs cs-fix stan test contract vendor-contract integration fixtures llms-check actionlint placeholder-check check release-check export-rulesets
