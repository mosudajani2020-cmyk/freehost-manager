# Linux Hosting Node & Customer Isolation Foundation (P2)

FreeHost Manager establishes a rigorous, multi-layered tenant isolation architecture. Application-level authorization alone is insufficient for multi-tenant shared hosting; **Linux operating system-level isolation** serves as the primary tenant boundary.

## 1. Target Architecture & Topology

```
                  FREEHOST MANAGER (Control Panel)
                                │
                                │ provisioning queue & worker
                                ▼
                      ┌───────────────────┐
                      │ LINUX HOSTING NODE│
                      │                   │
                      │  ┌─────────────┐  │
                      │  │ Customer A  │  │
                      │  │ Linux User  │  │
                      │  │ PHP-FPM A   │  │
                      │  │ /srv/freehost│  │
                      │  │ /fhm_1001   │  │
                      │  └─────────────┘  │
                      │                   │
                      │  ┌─────────────┐  │
                      │  │ Customer B  │  │
                      │  │ Linux User  │  │
                      │  │ PHP-FPM B   │  │
                      │  │ /srv/freehost│  │
                      │  │ /fhm_1002   │  │
                      │  └─────────────┘  │
                      └───────────────────┘
```

## 2. Customer Account Isolation Contract

Every hosting account maps deterministically through a trusted identity and layout service:

1. **Hosting Account ID** (e.g. `1001`)
2. **System Identity (`CustomerSystemIdentity`)**:
   - Username / Group: `fhm_1001`
   - UID / GID: `21001` (Base 20000 + account ID)
3. **Filesystem Layout (`HostingFilesystemLayout`)**:
   - Hosting Root: `/srv/freehost/customers` (configurable via `FHM_HOSTING_ROOT`)
   - Customer Home: `/srv/freehost/customers/fhm_1001`
   - Public Web: `/srv/freehost/customers/fhm_1001/public_html`
   - Logs: `/srv/freehost/customers/fhm_1001/logs`
   - Temp: `/srv/freehost/customers/fhm_1001/tmp`
   - PHP-FPM Pool Config: `/srv/freehost/customers/fhm_1001/fpm`
4. **PHP-FPM Pool (`PhpFpmPoolConfigGenerator`)**:
   - Dedicated socket: `/run/php/php8.3-fpm-fhm_1001.sock`
   - Isolated execution under user/group `fhm_1001`.

## 3. Security Threat Model & Mitigations

| Scenario | Threat Vector | Mitigating Layer |
| --- | --- | --- |
| **1. Cross-Customer File Access** | Customer A attempts to read Customer B's files (`/srv/freehost/customers/fhm_1002`). | **Linux Filesystem Permissions & User Isolation:** OS enforces that process `fhm_1001` cannot read files owned by `fhm_1002` (mode 0750/0700). |
| **2. Control Panel Compromise** | Customer A PHP attempts to read FreeHost Manager `.env`, database credentials, or source code. | **Path Isolation & Privilege Separation:** Control panel lives in `/var/www/freehost-manager` owned by `www-data`/`root`, completely separate from customer hosting roots (`/srv/freehost/customers`). |
| **3. Inter-Customer PHP Execution** | Customer A attempts to include or execute scripts belonging to Customer B. | **PHP-FPM Identity & open_basedir:** Each customer runs in their own PHP-FPM pool process (`fhm_1001` vs `fhm_1002`), restricted by `open_basedir` defense-in-depth. |
| **4. Path Traversal Attacks** | Customer input contains `../../` to escape web root. | **HostingFilesystemLayout & PathGuard:** Strict path normalization, boundary enforcement (`str_starts_with`), and traversal checks. |
| **5. Privilege Escalation** | Compromised customer web application attempts root or cross-user execution. | **OS Privilege Separation:** Customer PHP runs under unprivileged user `fhm_1001` without sudo or administrative capabilities. |
| **6. Resource Exhaustion / Denial of Service** | Customer A consumes 100% CPU/RAM or fills disk. | **ResourceIsolationPolicy & OS Limits:** cgroups, process limits (`pm.max_children`), memory limits (`memory_limit`), and disk/inode quotas. |

## 4. Local Development (Windows / AppServ)

On Windows development environments where Linux system users, PHP-FPM sockets, and cgroups are unavailable:
- `LocalMockIsolationProvider` simulates folder structures safely under `storage/hosting` without executing shell commands.
- `LinuxIsolationProvider` safely throws an exception if invoked on non-Linux runtimes, preventing unsafe platform mocks.
