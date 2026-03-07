# Warehouse AMC Clone Analysis: warehouse.mohjss.so → warehouse.damalnugal.com (Inventory)

This document lists everything related to **Warehouse AMC** (Average Monthly Consumption) in **warehouse.mohjss.so** and what is needed to clone/align it under **Inventory** in **warehouse.damalnugal.com**.

---

## 1. AMC-related assets (source: warehouse.mohjss.so)

| Category | File / area | In damalnugal? | Notes |
|----------|-------------|----------------|-------|
| **Model** | `app/Models/WarehouseAmc.php` | ✅ Yes | Same |
| **Controller** | `app/Http/Controllers/WarehouseAmcController.php` | ✅ Yes | Same methods; route names differ by app |
| **Service** | `app/Services/WarehouseAmcCalculationService.php` | ✅ Yes | Same |
| **Import** | `app/Imports/WarehouseAmcImport.php` | ✅ Yes | Same |
| **Export** | `app/Exports/WarehouseAmcExport.php` | ✅ Yes | Same |
| **Console** | `app/Console/Commands/GenerateWarehouseAMC.php` | ✅ Yes | Same |
| **Console** | `app/Console/Commands/GenerateRandomAmc.php` | ❌ No | Optional (dev/seed helper) |
| **Vue page** | `resources/js/Pages/Report/WarehouseAmc.vue` | ✅ Yes | **Uses `inventories.*` route names** |
| **Migrations** | `2025_05_27_064204_create_warehouse_amcs_table.php` | ✅ Yes | Same |
| **Migrations** | `2025_09_19_224334_add_warehouse_amcs_indexes.php` | ✅ Yes | Same |
| **Migrations** | `2025_09_19_214524_add_indexes_for_amc_calculation.php` | ❌ No | **Facility** tables (`monthly_consumption_*`), not `warehouse_amcs` — skip for warehouse AMC |
| **Routes** | Warehouse AMC under **inventory** prefix | ❌ No | damalnugal has AMC under **reports** only |
| **Report Schedules** | `warehouse_amc` in Settings → Report Schedules | ✅ Yes | Both have it |
| **Schedule def** | `resources/js/Pages/Settings/ReportSchedules/Index.vue` | ✅ Yes | damalnugal shows "Run now" for warehouse_amc; mohjss hides it |
| **EmailNotificationSetting** | `warehouseAmcSchedule()` | ✅ Yes | Same |
| **ReportScheduleController** | `warehouse_amc` → `warehouse:generate-amc` | ✅ Yes | Same |
| **Console schedule** | `Schedule::command('warehouse:generate-amc')` in `routes/console.php` | ✅ Yes | Same |
| **Inventory index** | Link to AMC from Inventory page | Different | mohjss: `inventories.warehouse-amc` "AMC"; damalnugal: `reports.warehouseAmc` "Warehouse AMC" |
| **Docs** | `docs/WAREHOUSE_AMC_SCHEDULE_INVESTIGATION.md` | ❌ No | Optional copy |

**Not needed for warehouse AMC (facility AMC):**

- `app/Services/AmcCalculationService.php` — used for **facility** AMC (`monthly_consumption_*`), not `warehouse_amcs`. No need to clone for warehouse AMC.

---

## 2. Route comparison (what to clone into Inventory)

| Purpose | mohjss.so (inventory) | damalnugal.com (current) | Action |
|--------|------------------------|---------------------------|--------|
| Report page | `GET /inventory/warehouse-amc` → `inventories.warehouse-amc` | Only under reports: `GET /reports/warehouse-amc` → `reports.warehouseAmc` | **Add** under inventory prefix with name `inventories.warehouse-amc` |
| Export | `GET /inventory/warehouse-amc/export` → `inventories.warehouse-amc.export` | `POST /reports/warehouse-amc/export` → `reports.warehouseAmc.export` | **Add** under inventory: **GET** (Vue uses `window.open` + query params). Add `inventories.warehouse-amc.export` |
| Template | `GET /inventory/warehouse-amc/template` → `inventories.warehouse-amc.template` | `GET /reports/warehouse-amc/download-template` → `reports.warehouseAmc.downloadTemplate` | **Add** under inventory: path `warehouse-amc/template`, name `inventories.warehouse-amc.template` |
| Import | `POST /inventory/warehouse-amc/import` → `inventories.warehouse-amc.import` | `POST /reports/warehouse-amc/import` → `reports.warehouseAmc.import` | **Add** under inventory: `inventories.warehouse-amc.import` |
| Import status | `GET /inventory/warehouse-amc/import-status/{importId}` → `inventories.warehouse-amc.import.status` | **Missing** | **Add** under inventory; Vue already calls this for polling |

**Critical:** `WarehouseAmc.vue` on damalnugal already uses `inventories.warehouse-amc`, `inventories.warehouse-amc.export`, `inventories.warehouse-amc.import`, `inventories.warehouse-amc.import.status`, and `inventories.warehouse-amc.template`. So without these routes under the inventory prefix, the AMC page will 404 or hit wrong routes.

---

## 3. Checklist: what to do to “clone AMC to Inventory” on damalnugal.com

1. **Routes (priority)**  
   In `routes/web.php`, inside the **same** `Route::prefix('inventory')` group that already has `inventories.index`, etc. (and same permission middleware as mohjss: `inventory-view,inventory-manage,inventory-adjust,inventory-transfer`), add:
   - `GET /warehouse-amc` → `WarehouseAmcController@index` → name `inventories.warehouse-amc`
   - `GET /warehouse-amc/export` → `WarehouseAmcController@export` → name `inventories.warehouse-amc.export`
   - `GET /warehouse-amc/template` → `WarehouseAmcController@downloadTemplate` → name `inventories.warehouse-amc.template`
   - `POST /warehouse-amc/import` → `WarehouseAmcController@import` → name `inventories.warehouse-amc.import`
   - `GET /warehouse-amc/import-status/{importId}` → `WarehouseAmcController@checkImportStatus` → name `inventories.warehouse-amc.import.status`

2. **Inventory Index link**  
   In `resources/js/Pages/Inventory/Index.vue`, change the AMC link from `route('reports.warehouseAmc')` to `route('inventories.warehouse-amc')` so it goes to the Inventory AMC page. Optionally match mohjss label (“AMC” instead of “Warehouse AMC”) and styling.

3. **Reports section (optional)**  
   - Either **remove** the duplicate Warehouse AMC routes from the `reports` prefix so there is a single entry point under Inventory, or  
   - **Keep** them for backward compatibility (same controller, different URLs).  
   If kept, ensure export under reports is GET if anything uses it with `window.open` + query params.

4. **Report Schedules “Run now” (optional)**  
   In `resources/js/Pages/Settings/ReportSchedules/Index.vue`, damalnugal currently shows “Run now” for `warehouse_amc`; mohjss hides it (`slug !== 'orders_quarterly' && slug !== 'warehouse_amc'`). Align with mohjss only if you want the same UX.

5. **Optional copies from mohjss**  
   - `app/Console/Commands/GenerateRandomAmc.php` (if you need dev/seed data).  
   - `docs/WAREHOUSE_AMC_SCHEDULE_INVESTIGATION.md` (documentation).

**No need to clone:**  
- `AmcCalculationService` or `2025_09_19_214524_add_indexes_for_amc_calculation.php` (facility AMC, not warehouse).  
- Model, Controller, Service, Import/Export, GenerateWarehouseAMC, migrations for `warehouse_amcs` — already present on damalnugal.

---

## 4. Summary

- **Backend:** damalnugal already has the same Warehouse AMC backend (model, controller, service, import/export, command, migrations).  
- **Gap:** AMC is only under **reports** and with different route names; the Vue expects **inventory** routes and one route is missing.  
- **Clone to Inventory:** Add the five warehouse-amc routes under the existing inventory prefix with the same names and HTTP methods as mohjss (export and template as GET; add `import-status/{importId}`). Point the Inventory index AMC link to `inventories.warehouse-amc`. Optionally align Report Schedules “Run now” and copy optional dev/docs files.
