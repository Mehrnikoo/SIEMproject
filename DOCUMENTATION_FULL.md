# DOCUMENTATION_FULL.md

> Updated: 2026-05-02  
> Purpose: single consolidated index and overview of the **current** documentation set.

---

## Current Documentation Files

The project documentation is intentionally limited to these files:

1. `README.md`  
   Main project documentation and architecture overview.

2. `QUICK_SETUP.md`  
   Fast setup, first-run verification, and syslog quick start.

3. `CONFIGURATION.md`  
   Full configuration reference, including syslog listener/device configuration.

4. `USER_GUIDE.md`  
   Usage flows, API usage, troubleshooting, maintenance, and operational guidance.

---

## Removed / Legacy Files

The following files are no longer part of the active docs set and should not be referenced:

- `SETUP_GUIDE.md`
- `SYSLOG_QUICK_START.md`
- `SYSLOG_SETUP.md`
- `README_SYSLOG.md`
- `IMPLEMENTATION_SUMMARY_SYSLOG.md`
- `SYSLOG_IMPLEMENTATION_COMPLETE.md`
- `DOCUMENTATION_INDEX.md`
- `DOCS_CONSOLIDATED.txt`

---

## Recommended Reading Order

### New users

1. `QUICK_SETUP.md`
2. `README.md`
3. `USER_GUIDE.md`

### Administrators / deployment

1. `QUICK_SETUP.md`
2. `CONFIGURATION.md`
3. `USER_GUIDE.md`

### Developers / maintainers

1. `README.md`
2. `CONFIGURATION.md`
3. `USER_GUIDE.md`

---

## Cross-Reference Checklist (validated)

- No links in this file point to deleted `.md` / `.txt` docs.
- Doc names match files currently in repository root.
- Syslog quick-start and syslog configuration references now map to:
  - `QUICK_SETUP.md` (quick path)
  - `CONFIGURATION.md` (full config path)

---

## Documentation Scope

### `README.md`

- What the project is
- Major components and architecture
- Core data paths
- Where to go next

### `QUICK_SETUP.md`

- 5-minute startup
- Verification commands
- Quick attack test
- Syslog listener quick test
- Docker quick path

### `CONFIGURATION.md`

- Python SIEM config keys
- PHP config keys
- permissions and storage setup
- syslog listener config
- network device syslog examples
- firewall/network policies

### `USER_GUIDE.md`

- dashboard/logs/VLAN usage
- API endpoint usage patterns
- troubleshooting matrix
- rotation/maintenance operations
- production guidance

---

## Notes

If documentation is changed again, update this file first so links and guidance never point to non-existent files.
