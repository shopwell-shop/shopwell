## MANDATORY: Docker Command Execution Override

**CRITICAL: ALL commands MUST be executed inside Docker containers**
**FORBIDDEN: Direct host execution - will cause failures**

### Required Execution Pattern:
```bash
docker exec -it shopwell_app <command>    # PHP/Composer/Console commands
docker exec -it shopwell_node <command>   # Node/NPM commands
```

### Examples:
- `docker exec -it shopwell_app composer cs-fix`
- `docker exec -it shopwell_app bin/console cache:clear`

**All commands from AGENTS.md must be prefixed with the appropriate docker exec pattern.**

**Container names:** `shopwell_app` (PHP), `shopwell_node` (Node) - verify with `docker ps`
