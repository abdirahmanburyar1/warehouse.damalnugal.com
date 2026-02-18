# Multiple Warehouse Instances — Different Directories, Different Databases

Each warehouse runs in **its own directory** and uses **its own database** (its own tables). For example:

- This app: `damal_db`
- Another: `galmudug_db`
- And so on

So there is **no sharing of tables** between warehouses. Each database has its own `cache`, `sessions`, `jobs`, and business tables.

---

## What you need per warehouse

| Item | Purpose |
|------|--------|
| **Different directory** | e.g. `/var/www/warehouse.damalnugal.com`, `/var/www/warehouse.galmudug.com` |
| **Different `DB_DATABASE`** | e.g. `damal_db`, `galmudug_db` — each warehouse has its own database and own tables. |
| **One cron line per directory** | So the correct app’s Laravel scheduler runs. |
| **One queue worker per directory** | Run `php artisan queue:work` from each app’s directory so each processes its own `jobs` table. |

Same MySQL server is fine; only the **database name** must be different per warehouse.

---

## Cron (one line per warehouse)

```cron
* * * * * cd /var/www/warehouse.damalnugal.com && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /var/www/warehouse.galmudug.com && php artisan schedule:run >> /dev/null 2>&1
```

Each line runs the scheduler for that directory only. No collision.

---

## Optional: Redis and app identity

- **Redis**  
  If multiple warehouses use the same Redis server, set a **unique prefix** per app so keys don’t mix (or use different `REDIS_DB` numbers):
  - `REDIS_PREFIX=damal_` / `REDIS_PREFIX=galmudug_`
  - Config falls back to `APP_NAME` for the prefix when `REDIS_PREFIX` is not set, so **different `APP_NAME`** per warehouse is enough if you don’t set `REDIS_PREFIX`.

- **`APP_NAME`**  
  Can be different per warehouse (e.g. “Damal Nugal”, “Galmudug”) for clarity and for session cookie name if needed. Not required for DB isolation, since each app already has its own database.

---

## Checklist: two warehouses (damal_db vs galmudug_db)

| Item | Warehouse 1 (Damal) | Warehouse 2 (Galmudug) |
|------|---------------------|------------------------|
| Directory | `/var/www/warehouse.damalnugal.com` | `/var/www/warehouse.galmudug.com` |
| `DB_DATABASE` | `damal_db` | `galmudug_db` |
| Tables | Own set (e.g. `damal_db`.`inventories`, …) | Own set (e.g. `galmudug_db`.`inventories`, …) |
| Cron | `cd …/warehouse.damalnugal.com && php artisan schedule:run` | `cd …/warehouse.galmudug.com && php artisan schedule:run` |
| Queue worker | Run from directory 1 | Run from directory 2 |

Because each warehouse has its **own database**, there are no cache, queue, or session collisions between them. Just keep **different directories** and **different `DB_DATABASE`** (e.g. `damal_db`, `galmudug_db`) and one cron + one worker per directory.
