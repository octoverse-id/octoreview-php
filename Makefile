.DEFAULT_GOAL := help
SHELL := /bin/bash

# Bootstrap Makefile for octoreview-php. Targets are added by the PRs that add the code they gate;
# `make check` stays toolchain-only (no Docker, no network) and `make release-check` is the full gate.

help: ## List available targets
	@grep -E '^[a-zA-Z0-9_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  %-20s %s\n", $$1, $$2}'

llms-check: ## Fail when llms.txt links a missing local path (and prove the check can fail)
	@./scripts/check-llms-txt.sh
	@./scripts/check-llms-txt-test.sh

actionlint: ## Lint GitHub Actions workflows (needs actionlint on PATH)
	@actionlint -color

placeholder-check: ## Fail while any bootstrap CI placeholder job remains
	@if grep -rn 'octoreview:placeholder' .github/workflows; then \
		echo "placeholder-check: bootstrap placeholder jobs remain; replace them before a release" >&2; exit 1; \
	fi; echo "placeholder-check OK"

check: llms-check ## Fast gate (toolchain only)

release-check: check placeholder-check ## Full pre-release gate (fails while placeholders remain)

export-rulesets: ## Export the live rulesets into .github/rulesets/ (needs gh with repo admin)
	@./scripts/export-rulesets.sh

.PHONY: help llms-check actionlint placeholder-check check release-check export-rulesets
