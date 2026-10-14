# AGENTS.md

* Never commit code. Human supervisors will do this for you.
* Use Podman containers for all development, testing, and software installation. Do NOT install software directly on the host machine.
  - Exception is PHP libraries which can be installed using `composer` as dependencies of the system under development.
  - For development and testing, the project should be bind-mounted to a PHP container.
* After performing analysis, code reviews or planning sessions always write the results to a fresh Markdown file for human reviewers to read and share.
* Use sub-agents to implement plans to protect your context window and parallelize work where possible.

# Coding standards

* Use the PSR-12 coding standard for PHP source code, except in test fixtures where necessary
* Use PSR-4 autoloading

# Architectural validation

* After any significant code changes, run the self-analysis tool using: `php tools/self-analysis/run-analysis.php && php tools/self-analysis/generate-report.php`
* Use the previous report in `tools/self-analysis/reports/` as a baseline for comparison
* The architecture must not drift: circular dependencies, layer violations, and anti-patterns should not increase
* All new code must pass the architectural validation rules defined in `tools/self-analysis/validation-rules.php`