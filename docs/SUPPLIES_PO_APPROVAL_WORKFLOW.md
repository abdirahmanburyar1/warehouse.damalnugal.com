# Purchase Order (EditPo) approval workflow

This describes the approval workflow on the **Edit Purchase Order** page (`Supplies/EditPo.vue`) and which permissions control each action. Email recipients for "next action" notifications are **all users who have the permission required for that next action**.

---

## Status flow

```
  Draft (pending)  →  Reviewed  →  Approved
                         ↓
                     Rejected
```

| UI status | Backend `status` | Meaning |
|-----------|------------------|--------|
| **Draft** | `pending` | PO created or saved; not yet reviewed |
| **Reviewed** | `reviewed` | Someone clicked "Review"; waiting for Approve or Reject |
| **Approved** | `approved` | Someone clicked "Approve"; workflow complete |
| **Rejected** | `rejected` | Someone clicked "Reject" (with reason); workflow complete |

---

## Actions and permissions (from EditPo.vue)

| Step | Action | Permission required | When button is enabled |
|------|--------|---------------------|-------------------------|
| 1 | **Review** | `purchase-order-review` | PO is Draft (no `reviewed_at`), and user has permission |
| 2a | **Approve** | `purchase-order-approve` | PO is Reviewed (`reviewed_at` set), not approved/rejected, and user has permission |
| 2b | **Reject** | `purchase-order-reject` | PO is Reviewed, not approved/rejected, and user has permission |
| (edit) | **Save / Update PO** | `purchase-order-edit` | PO not approved; user can edit details and items |

So:

- **Next action when PO is Draft:** Review → notify users with **`purchase-order-review`**.
- **Next action when PO is Reviewed:** Approve or Reject → notify users with **`purchase-order-approve`** (and optionally **`purchase-order-reject`**, or same email for “approve or reject” to both).

---

## “Next action” email recipients (permission-based)

| Event | Next action | Recipients (who gets the email) |
|-------|-------------|----------------------------------|
| PO created or saved and still **pending** | Review | All users with permission **purchase-order-review** |
| PO **reviewed** (status = reviewed) | Approve or Reject | All users with **purchase-order-approve** (and optionally **purchase-order-reject**) |
| PO **approved** | — | Optional: notify creator only (outcome, not “next action”) |
| PO **rejected** | — | Optional: notify creator (and include rejection reason) |

Implementation will:

1. On **PO created** (or saved while pending): find all users with `purchase-order-review` and send “Purchase order {po_number} needs your review” with link.
2. On **PO reviewed**: find all users with `purchase-order-approve` (and optionally `purchase-order-reject`) and send “Purchase order {po_number} has been reviewed and is ready for approval” with link.

No fixed list of reviewers/approvers: **recipients = users who have the next-action permission**.
