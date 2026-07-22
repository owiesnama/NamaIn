# Offline POS — Support Playbook

> Audience: support staff. Plain language, no code. One page per failure mode
> (Design 04 §6.4). Where to look: the tenant's **Devices** page, the tenant's
> **Reconciliation** inbox, and the super-admin **Device fleet / Pilot health**
> pages.

---

## 1. Device lost or stolen

**Symptoms:** the store owner reports a register device missing, or a device
shows activity from an unexpected place or time.

**What to do:**

1. Open the tenant's **Devices** page and find the device.
2. Click **Revoke**. This kills the device's access immediately — the next time
   the device tries to talk to the server it is refused and wipes its own local
   data on launch.
3. The device row will show "≈ N items may be lost." This is the last number of
   unsynced sales the device reported before it disappeared. It is approximate
   and honest: those sales were made offline and never reached the cloud, so
   they cannot be recovered.
4. If the store is getting a replacement device for the same register, enroll a
   new device on that register from the Devices page (the sale numbering
   continues automatically).

**Important:** Revoke is for lost/stolen devices only. If the store still has
the device and just wants to swap hardware, use **Replace** instead (see the
notes under "Stuck outbox" — Replace refuses to run until the device has
finished sending its sales, so nothing is lost).

---

## 2. Oversell storm

**Symptoms:** the owner sees many "Oversell" items in the Reconciliation inbox
after a busy stretch offline — often after two registers sold the same product
at the same time without knowing about each other.

**What to say first:** reassure the owner. **All sales are safe and recorded;
nothing was blocked at the till.** The system deliberately lets offline sales
go through and flags the stock gap for review instead of stopping a customer
at checkout.

**What to do:**

1. Open the **Reconciliation** inbox and filter by type "Oversell."
2. Work through the items in bulk, in this order of preference:
   - **Adjust** — the owner does a physical count of the product and enters the
     real quantity. Best option; fixes the number for good.
   - **Transfer** — bring units from another warehouse/storage to cover the
     shortfall.
   - **Shrinkage** — accept the loss and write the missing units off.
3. Remind the owner the daily summary email lists open items — there is no
   per-item email, so a storm never floods their inbox.

---

## 3. Stuck outbox

**Symptoms:** on the Devices page a device shows health **"Outbox stuck"** —
it is online but its pending count is not going down, and the "pending age" is
climbing.

**What to do, in order:**

1. **Check connectivity at the store.** Can the device reach the internet at
   all? A device that is fully offline shows "Offline," not "Outbox stuck" —
   stuck means it can talk to us but its sales are not being accepted.
2. **Check for an old app version.** If the app is too old the server refuses
   its sync until it updates ("upgrade required" — see failure mode 4).
3. **Check the Reconciliation inbox for "Parked mutation" items** from this
   device. A parked item means the server permanently refused one of the
   device's sales (for example, malformed data). The parked item shows the
   reason and the full details of what the device sent. The rest of the queue
   usually keeps flowing; resolve the parked item by acknowledging it after
   review.
4. **Escalate to engineering** only if the store has connectivity, the app is
   current, there are no parked items, and the outbox still will not drain.

---

## 4. Upgrade-required deadlock

**Symptoms:** the device shows an "update required" message; it still sells
offline but its sales are not reaching the cloud. On the Devices page the
pending count grows while the device looks otherwise healthy.

**What this means:** the desktop app version is older than the minimum the
server accepts. The server refuses to sync with it until it updates. **No data
is lost** — everything the store sells stays safely queued on the device.

**What to do:**

1. Confirm whether the desktop app's auto-update ran. If not, have the store
   restart the app (updates install on launch) or push the new build.
2. Tell the store they can keep selling normally in the meantime — offline
   selling is not affected.
3. Once the app updates, the queued sales upload automatically and the pending
   count drains to zero. No manual recovery is needed.
4. If the device cannot be updated (very old OS, broken updater), treat it as a
   planned swap: let it finish updating/syncing if at all possible, then use
   **Replace** on the Devices page.
