# Sidebar vs database permissions comparison

The sidebar uses permission keys in **underscore** form (e.g. `order_view`). The backend sends `auth.can` with the same convention: DB permission names are converted with `str_replace(['.', '-'], '_', $permission)` in `HandleInertiaRequests`.

## Sidebar menu → permission key → DB permission

| Sidebar menu | Vue key (`can('...')`) | DB permission name | Match |
|--------------|------------------------|--------------------|-------|
| Dashboard    | `dashboard_view`       | `dashboard-view`   | ✅ |
| Orders       | `order_view`           | `order-view`       | ✅ |
| Transfers    | `transfer_view`       | `transfer-view`    | ✅ |
| Product List | `product_view`        | `product-view`     | ✅ |
| Inventory    | `inventory_view`       | `inventory-view`   | ✅ |
| Expires      | `inventory_view`       | `inventory-view`   | ✅ (same as Inventory) |
| Wastages     | `wastage_view`         | `wastage-view`     | ✅ |
| Supplies     | `supply_view`         | `supply-view`      | ✅ |
| Reports      | `reports_view`         | `reports-view`     | ✅ |
| Facilities   | `facility_view`        | `facility-view`    | ✅ |
| Assets       | `asset_view`           | `asset-view`      | ✅ |
| Settings     | `system_settings`      | `system-settings`  | ✅ |

All 12 sidebar checks map to an existing permission in the database. No mismatches.

---

## DB permissions related to sidebar modules (not used in sidebar)

These exist in the DB and map to the same “module” as a sidebar item, but the sidebar only checks the one “view” permission above. Listed for reference.

| Module / Menu | Sidebar uses | Other DB permissions (same area) |
|---------------|--------------|-----------------------------------|
| **Settings**  | `system_settings` | `permission-manage` → `permission_manage`, `setting-view` → `setting_view` |
| **Wastages**  | `wastage_view`    | `liquidate-view`, `liquidate-create`, `liquidate-edit`, `liquidate-delete` |
| **Reports**   | `reports_view`    | `reports-export`, `report-view`, `report-export`, `report-physical-count-*` |

If you want “Settings” to show for users who can only manage permissions (no system settings), the sidebar could use:  
`can('system_settings') || can('permission_manage')`.

---

## Conversion reference (HandleInertiaRequests)

- DB: `system-settings` → frontend: `system_settings`
- DB: `order-view` → frontend: `order_view`
- DB: `category.view` → frontend: `category_view`

So any DB permission name, with `.` and `-` replaced by `_`, is the key to use in `can('...')` in the sidebar.
