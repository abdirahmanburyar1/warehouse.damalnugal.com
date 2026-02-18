# Permissions (UI list) vs Sidebar comparison

Your UI permission list is compared to what the **sidebar** uses to show/hide menu items.  
Frontend keys: DB names with `.` and `-` replaced by `_` (e.g. `system-settings` → `system_settings`).

---

## Permissions that control the sidebar (used in sidebar)

These are the only permissions that currently control sidebar visibility. If a user has one of these (or is admin), they see that menu.

| # | Display name (from your list) | DB name | Sidebar key | Sidebar menu |
|---|-------------------------------|---------|-------------|--------------|
| 1 | View Dashboard | `dashboard-view` | `dashboard_view` | Dashboard |
| 2 | View Orders | `order-view` | `order_view` | Orders |
| 3 | View Transfers | `transfer-view` | `transfer_view` | Transfers |
| 4 | View Products | `product-view` | `product_view` | Product List |
| 5 | View Inventory | `inventory-view` | `inventory_view` | Inventory, Expires |
| 6 | View Wastages | `wastage-view` | `wastage_view` | Wastages |
| 7 | View Supplies | `supply-view` | `supply_view` | Supplies |
| 8 | View Reports | `reports-view` | `reports_view` | Reports |
| 9 | View Facilities | `facility-view` | `facility_view` | Facilities |
| 10 | View Assets | `asset-view` | `asset_view` | Assets |
| 11 | System Settings | `system-settings` | `system_settings` | Settings |

So: **11 distinct permissions** from your list are used for sidebar visibility.

---

## Permissions from your list NOT used in the sidebar

All other permissions in your list exist in the DB and can be used for in-page actions (buttons, routes, etc.), but they do **not** control whether a sidebar menu appears.

### System / admin

| Display name | DB name | Sidebar key | Note |
|--------------|---------|-------------|------|
| Manage System | `manage-system` | `manage_system` | Admin-level; not used for sidebar |
| View System | `view-system` | `view_system` | Not used for sidebar |
| View Only Access | `view-only-access` | `view_only_access` | Not used for sidebar |
| System Manager | `manager-system` | `manager_system` | Not used for sidebar |
| Manage Permissions | `permission-manage` | `permission_manage` | Not used for sidebar (Settings menu uses only `system_settings`) |
| View Settings | `setting-view` | `setting_view` | Not used for sidebar |

### Users

| Display name | DB name | Sidebar key |
|--------------|---------|-------------|
| View Users | `user-view` | `user_view` |
| Create Users | `user-create` | `user_create` |
| Edit Users | `user-edit` | `user_edit` |
| Delete Users | `user-delete` | `user_delete` |

*(There is no “Users” item in the sidebar; user management is under Settings.)*

### Facilities (except View)

| Display name | DB name | Sidebar key |
|--------------|---------|-------------|
| Create Facilities | `facility-create` | `facility_create` |
| Edit Facilities | `facility-edit` | `facility_edit` |
| Delete Facilities | `facility-delete` | `facility_delete` |
| Import Facilities | `facility-import` | `facility_import` |
| Manage Facilities | `facility-manage` | `facility_manage` |

### Products (except View)

| Display name | DB name | Sidebar key |
|--------------|---------|-------------|
| Create Products | `product-create` | `product_create` |
| Edit Products | `product-edit` | `product_edit` |
| Delete Products | `product-delete` | `product_delete` |
| Import Products | `product-import` | `product_import` |
| Manage Products | `product-manage` | `product_manage` |

### Inventory (except View)

| Display name | DB name | Sidebar key |
|--------------|---------|-------------|
| Adjust Inventory | `inventory-adjust` | `inventory_adjust` |
| Transfer Inventory | `inventory-transfer` | `inventory_transfer` |
| Manage Inventory | `inventory-manage` | `inventory_manage` |

### Warehouses (no sidebar menu)

| Display name | DB name | Sidebar key |
|--------------|---------|-------------|
| View Warehouses | `warehouse-view` | `warehouse_view` |
| Manage Warehouses | `warehouse-manage` | `warehouse_manage` |

*(No “Warehouses” item in sidebar.)*

### Orders (except View)

| Display name | DB name | Sidebar key |
|--------------|---------|-------------|
| Create Orders | `order-create` | `order_create` |
| Edit Orders | `order-edit` | `order_edit` |
| Delete Orders | `order-delete` | `order_delete` |
| Manage Orders | `order-manage` | `order_manage` |
| Review Orders | `order-review` | `order_review` |
| Approve Orders | `order-approve` | `order_approve` |
| Reject Orders | `order-reject` | `order_reject` |
| Process Orders | `order-processing` | `order_processing` |
| Dispatch Orders | `order-dispatch` | `order_dispatch` |

### Transfers (except View)

| Display name | DB name | Sidebar key |
|--------------|---------|-------------|
| Create Transfers | `transfer-create` | `transfer_create` |
| Edit Transfers | `transfer-edit` | `transfer_edit` |
| Delete Transfers | `transfer-delete` | `transfer_delete` |
| Manage Transfers | `transfer-manage` | `transfer_manage` |
| Review Transfers | `transfer-review` | `transfer_review` |
| Approve Transfers | `transfer-approve` | `transfer_approve` |
| Reject Transfers | `transfer-reject` | `transfer_reject` |
| Process Transfers | `transfer-processing` | `transfer_processing` |
| Dispatch Transfers | `transfer-dispatch` | `transfer_dispatch` |
| Receive Transfers | `transfer-receive` | `transfer_receive` |

### Assets (except View)

| Display name | DB name | Sidebar key |
|--------------|---------|-------------|
| Create Assets | `asset-create` | `asset_create` |
| Edit Assets | `asset-edit` | `asset_edit` |
| Delete Assets | `asset-delete` | `asset_delete` |

### Liquidations / Wastages

| Display name | DB name | Sidebar key | Note |
|--------------|---------|-------------|------|
| View Liquidations | `liquidate-view` | `liquidate_view` | Sidebar uses **View Wastages** (`wastage_view`) for “Wastages” menu |
| Create Liquidations | `liquidate-create` | `liquidate_create` |
| Edit Liquidations | `liquidate-edit` | `liquidate_edit` |
| Delete Liquidations | `liquidate-delete` | `liquidate_delete` |

### Supplies (except View)

| Display name | DB name | Sidebar key |
|--------------|---------|-------------|
| Create Supplies | `supply-create` | `supply_create` |
| Edit Supplies | `supply-edit` | `supply_edit` |
| Delete Supplies | `supply-delete` | `supply_delete` |

### Purchase Orders (no dedicated sidebar item; under Supplies)

| Display name | DB name | Sidebar key |
|--------------|---------|-------------|
| View Purchase Orders | `purchase-order-view` | `purchase_order_view` |
| Create Purchase Orders | `purchase-order-create` | `purchase_order_create` |
| … (review, approve, reject, etc.) | … | … |

### Packing Lists

| Display name | DB name | Sidebar key |
|--------------|---------|-------------|
| View Packing Lists | `packing-list-view` | `packing_list_view` |
| Create / Edit / Delete / Review / Approve / Reject | … | … |
| Update Packing Lists | `packing-list-update` | `packing_list_update` |

### Reports (except View)

| Display name | DB name | Sidebar key |
|--------------|---------|-------------|
| Export Reports | `reports-export` | `reports_export` |
| View Reports (Legacy) | `report-view` | `report_view` |
| Export Reports (Legacy) | `report-export` | `report_export` |
| View/Generate/Review/Approve Physical Count Report | `report-physical-count-*` | `report_physical_count_*` |

### Categories & roles (no sidebar menu)

| Display name | DB name | Sidebar key |
|--------------|---------|-------------|
| View Categories | `category.view` | `category_view` |
| Create / Edit / Delete Categories | `category.create` etc. | `category_create` etc. |
| View Roles | `role.view` | `role_view` |

### MOH Inventory

| Display name | DB name | Sidebar key |
|--------------|---------|-------------|
| View MOH Inventory | `moh-inventory-view` | `moh_inventory_view` |
| Create / Review / Approve / Reject MOH Inventory | … | … |

### Received Backorders

| Display name | DB name | Sidebar key |
|--------------|---------|-------------|
| View / Create / Review / Approve / Reject Received Backorders | `received-backorder-*` | `received_backorder_*` |

### Suppliers

| Display name | DB name | Sidebar key |
|--------------|---------|-------------|
| View Suppliers | `supplier-view` | `supplier_view` |

---

## Summary

| | Count |
|--|--------|
| Permissions in your UI list (unique) | ~70+ |
| Permissions used in sidebar | **11** |
| Permissions not used in sidebar | All others |

- Sidebar only checks **one “view” (or equivalent) permission per menu**. Create/Edit/Delete/Approve/etc. are for in-page and route protection, not sidebar visibility.
- **Settings** menu: currently only `system_settings` is used. If you want users with only “Manage Permissions” to see Settings, add `can('permission_manage')` (or `setting_view` if you prefer) in the layout.
- **Wastages** menu: sidebar uses `wastage_view` (“View Wastages”). “View Liquidations” (`liquidate_view`) is a separate permission and is not used for the sidebar.

If you tell me which menu or permission you want to change (e.g. Settings, Users, Warehouses), I can suggest the exact `can('...')` change or new sidebar item.
