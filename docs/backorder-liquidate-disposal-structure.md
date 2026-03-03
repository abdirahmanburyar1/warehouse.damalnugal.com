# Back Order – Liquidate & Disposal Tables: Structure and Requirements

## 1. Back orders table (`back_orders`)

| Column | Type | Notes |
|--------|------|--------|
| id | bigint | PK |
| back_order_number | string, unique | Auto-generated (e.g. BO-2025-0001) |
| packing_list_id | FK | Required, cascade on delete |
| order_id | FK, nullable | Nullable for packing-list-only back orders |
| transfer_id | FK, nullable | Nullable for packing-list-only back orders |
| back_order_date | date | |
| total_items | int | |
| total_quantity | int | |
| status | enum | pending, processing, completed, cancelled |
| notes | text | |
| attach_documents | json | |
| source_type | string | e.g. 'packing_list' |
| created_by, updated_by | FK users | |

- One back order is created per packing list (or context). Multiple **packing list differences** (rows in the back-order items table) can belong to the same back order.
- When creating a **liquidation** or **disposal** from the Back Order page, the same `back_order_id` can be reused so one back order can have one liquidate and/or one disposal (each with many items).

---

## 2. Liquidates table (`liquidates`)

**Purpose:** One header record per “liquidation” (e.g. from one back order). Items are in `liquidate_items`.

| Column | Type | Notes |
|--------|------|--------|
| id | bigint | PK |
| liquidate_id | string, unique | Display ID (e.g. 0000001), auto-generated |
| liquidated_by | FK users, nullable | User who created it |
| liquidated_at | timestamp | |
| status | string | pending → reviewed → approved / rejected |
| source | string, nullable | From BackOrder.source_type (e.g. packing_list) |
| facility | string, nullable | |
| warehouse | string, nullable | |
| transfer_id | FK, nullable | Optional link to transfer |
| order_id | FK, nullable, unique | Optional link to order |
| packing_list_id | FK, nullable, unique | Optional link to packing list |
| back_order_id | FK, nullable, unique | **Link to back order** (one liquidate per back order) |
| inventory_adjustment_id | FK, nullable | Optional |
| reviewed_by, reviewed_at | FK, timestamp | |
| approved_by, approved_at | FK, timestamp | |
| rejected_by, rejected_at, rejection_reason | FK, timestamp, text | |

**From Back Order page (SupplyController::liquidate):**

- If no liquidation exists for the given `back_order_id`, a new **liquidate** row is created with:
  - `liquidated_by`, `liquidated_at`, `status` = 'pending', `source` = back order’s `source_type`
  - `back_order_id`, `packing_list_id`, `order_id` (as purchase_order_id), `transfer_id`
- If one already exists for that back order, only a new **liquidate_item** is added (see below).

---

## 3. Liquidate items table (`liquidate_items`)

**Purpose:** One row per product/quantity being liquidated (e.g. one line from a back order difference).

| Column | Type | Notes |
|--------|------|--------|
| id | bigint | PK |
| liquidate_id | FK liquidates | Required, cascade on delete |
| product_id | FK products, nullable | |
| quantity | integer | |
| unit_cost | decimal(10,2), nullable | From packing list item or PO item |
| total_cost | decimal(10,2), nullable | unit_cost * quantity |
| barcode | string, nullable | From packing list item |
| expire_date | date, nullable | |
| batch_number | string, nullable | |
| uom | string, nullable | |
| location | string, nullable | |
| note | text, nullable | User note (required in Back Order UI) |
| type | string, nullable | e.g. status (Missing, etc.) |
| attachments | json, nullable | PDF paths etc. |

**From Back Order page:**

- Request fields used: `back_order_id`, `id` (packing_list_difference id), `product_id`, `packing_list_item_id`, `quantity`, `note`, `type` (status), `attachments[]`.
- Controller also sends: `packing_list_id`, `purchase_order_id`, `transfer_id` for the **liquidate** header.
- Unit cost comes from `PackingListItem` (or its purchase order item); total cost = unit_cost * quantity.
- After creating the item, the corresponding **packing_list_difference** row is marked `finalized = true`.

---

## 4. Disposals table (`disposals`)

**Purpose:** One header record per “disposal” (e.g. from one back order). Items are in `disposal_items`.

| Column | Type | Notes |
|--------|------|--------|
| id | bigint | PK |
| disposal_id | string, unique | Display ID, auto-generated |
| disposed_by | FK users, nullable | |
| disposed_at | timestamp | |
| status | string | pending → reviewed → approved / rejected |
| source | string, nullable | From BackOrder.source_type |
| facility | string, nullable | |
| warehouse | string, nullable | |
| transfer_id | FK, nullable | |
| order_id | FK, nullable, unique | |
| packing_list_id | FK, nullable, unique | |
| back_order_id | FK, nullable, unique | **Link to back order** (one disposal per back order) |
| reviewed_by, reviewed_at | FK, timestamp | |
| approved_by, approved_at | FK, timestamp | |
| rejected_by, rejected_at, rejection_reason | FK, timestamp, text | |

**From Back Order page (SupplyController::dispose):**

- If no disposal exists for the given `back_order_id`, a new **disposal** row is created with:
  - `disposed_by`, `disposed_at`, `status` = 'pending', `source` = back order’s `source_type`, `back_order_id`
- If one already exists, only a new **disposal_item** is added.
- Note: `packing_list_id`, `order_id`, `transfer_id` are not set when creating from back order (nullable).

---

## 5. Disposal items table (`disposal_items`)

**Purpose:** One row per product/quantity being disposed.

| Column | Type | Notes |
|--------|------|--------|
| id | bigint | PK |
| disposal_id | FK disposals | Required, cascade on delete |
| product_id | FK products | Required |
| quantity | decimal(10,2) | |
| unit_cost | decimal(10,2) | From packing list item |
| total_cost | decimal(10,2) | |
| barcode | string, nullable | |
| expire_date | date, nullable | |
| batch_number | string, nullable | |
| uom | string, nullable | |
| location | string, nullable | |
| facility | string, nullable | |
| warehouse | string, nullable | |
| note | text, nullable | User note (required in Back Order UI) |
| type | string, nullable | e.g. status (Damaged, Lost, Expired, etc.) |
| attachments | json, nullable | |

**From Back Order page:**

- Request fields used: `back_order_id`, `id` (packing_list_difference id), `packing_list_item_id`, `quantity`, `note`, `type` (status), `attachments[]`.
- `product_id` is taken from `PackingListItem` in the controller, not sent in the form.
- Unit/total cost from packing list item (or PO item); warehouse/facility from current user context.
- Corresponding **packing_list_difference** is set `finalized = true` after creating the disposal item.

---

## 6. Back Order page flow (Supplies/BackOrder.vue)

- User selects a **packing list** → loads back order info and **packing list differences** (grouped by product / packing list item / date).
- Each row has a **status**: Missing, Damaged, Lost, Expired, Low quality.
- **Actions per row:**
  - **Receive** – always available.
  - **Liquidate** – only when status = **Missing** (calls `back-order.liquidate`).
  - **Dispose** – when status is **not** Missing (Damaged, Lost, Expired, Low quality) (calls `back-order.dispose`).

**Liquidate modal:** quantity (read-only from row), note (required), attachments (PDF). Submit sends:  
`id`, `product_id`, `packing_list_item_id`, `quantity`, `note`, `back_order_id`, `packing_list_id`, `purchase_order_id`, `type`, `attachments[]`.  
(transfer_id is not sent in the current frontend; controller may use null.)

**Dispose modal:** same shape (quantity, note, attachments). Submit sends:  
`id`, `packing_list_item_id`, `quantity`, `note`, `back_order_id`, `type`, `attachments[]`.  
Product id is resolved from packing list item on the server.

---

## 7. Summary

| Entity | Linked to back order | When created from Back Order page |
|--------|----------------------|-----------------------------------|
| **liquidates** | Yes, `back_order_id` (unique) | First “Liquidate” for that back order creates header; later ones add items only. |
| **liquidate_items** | Via liquidate_id → liquidates.back_order_id | One per “Liquidate” click (Missing row). |
| **disposals** | Yes, `back_order_id` (unique) | First “Dispose” for that back order creates header; later ones add items only. |
| **disposal_items** | Via disposal_id → disposals.back_order_id | One per “Dispose” click (Damaged/Lost/Expired/Low quality row). |

Both flows mark the related **packing_list_difference** as `finalized` and optionally update **inventory_allocations** (received_quantity). Notifications (e.g. liquidation-review, disposal-review) are sent when a **new** liquidate or disposal header is created.

---

## 8. The `source` field (liquidates.source / disposals.source)

**Where it comes from:** When creating a liquidate or disposal from the Back Order page, **source** is taken from the back order’s **source_type**, with a fallback when that is null.

**Possible values:**

| Value           | Meaning | Where back_order.source_type is set |
|----------------|--------|--------------------------------------|
| **packing_list** | Back order created from packing list differences (e.g. Supplies Back Order flow). | `SupplyController`: when creating/updating back order from packing list (firstOrCreate with `source_type => 'packing_list'`). |
| **transfer**     | Back order created from a transfer (e.g. transfer receive with differences).     | `TransferController`: when creating back order for transfer packing list differences (`source_type => 'transfer'`). |
| **order**       | Back order created from an order (if that flow sets source_type).                 | Set wherever back orders are created from orders (e.g. order-based back order flows). |
| **back_order**  | Fallback when source_type is null and no transfer/order/packing_list FK is set.  | `SupplyController::resolveSourceFromBackOrder()` when no other value can be inferred. |

**Resolution logic (in code):** `SupplyController::resolveSourceFromBackOrder(BackOrder $backOrder)`:

1. If `back_order.source_type` is non-empty → use it.
2. Else if back order has `transfer_id` → `'transfer'`.
3. Else if back order has `order_id` → `'order'`.
4. Else if back order has `packing_list_id` → `'packing_list'`.
5. Else → `'back_order'`.

So **source** always reflects the origin of the back order (and thus of the liquidation/disposal) and is never stored as null when creating from back order.
