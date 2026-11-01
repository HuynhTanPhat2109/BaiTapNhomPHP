# Booking → payment handoff

This PR does not create payment/invoice rows, QR codes or successful payments.
Run database/migrations/20261031_booking_request_key.sql once on an existing database;
fresh installs use the main schema. Do not rerun older migrations. Both new fields are
nullable for old bookings. The second field binds a request key to customer + canonical
slots + services + note. A retry returns the original booking, including expired bookings.

## Entry points

- BookingService::quote(selection, extras): authoritative prices and availability; no writes.
- BookingService::confirm(customerId, serverDraftKey, selection, extras, acceptedQuoteHash):
  transaction; authenticated caller derives customerId, never HTTP input. Returns booking ID.
- owned(id, customerId): enforces ownership and lazily expires an overdue pending booking.
- expirePending(): optionally run via `php bin/expire-bookings.php --run` every minute.
- lockPayable(id): **requires a caller-owned transaction**, locks booking and rejects anything
  except CHO_THANH_TOAN with expiry strictly after server time. It performs no payment writes.

## Required contract for Duy's future transaction

1. Independently authenticate/verify provider callback signature, amount, currency, reference
   and uniqueness of provider transaction. Never trust browser success redirects.
2. Use the same PDO connection and parent lock order as booking writers:
   company → facility → sports ascending → court types ascending → courts ascending →
   services ascending → booking/payment rows. Read IDs first, verify relationships after locking.
   Booking creation also locks the requesting customer/account before this chain.
3. Call lockPayable while holding these locks. Recheck expiry immediately before the conditional
   state update (WHERE state=CHO_THANH_TOAN AND expiry>CURRENT_TIMESTAMP). A callback after expiry
   must not reclaim a slot, even if no other booking has used it yet. Reconcile received money
   separately (existing payment state CAN_DOI_SOAT); do not silently confirm.
4. Pending reservations are derived from chi_tiet_dich_vu_dat joined to unexpired pending bookings.
   `dich_vu.so_luong_ton` is not decremented during a hold. On successful payment, atomically
   decrement tracked stock by snapshot quantities and transition booking to DA_XAC_NHAN.
   This removes its virtual reservation in the same transaction, avoiding double counting.
   Repeated callbacks must not decrement stock or issue invoices twice.
5. Write payment + invoice + booking transition + stock deduction together; rollback all on error.
   Provider/network calls must occur outside the transaction. Never hold locks for six minutes.

Expired/cancelled pending rows release virtual stock automatically without any restock write.
The availability query excludes expiry<=now even if the scheduler has not updated HET_HAN yet.
CHO_THUE is display-only until Vũ defines rental availability/return rules. NULL stock means
untracked stock, not a guarantee of coach or rental capacity.

## Customer flow

Public POST quote/start/review require CSRF. Drafts contain only normalized IDs/date/times,
extras, last server quote hash and a random request key; at most 10, lifetime two hours.
They are not bookings and do not reserve inventory. Login rotates the session and CSRF,
preserving only compatible booking drafts and using a fixed internal return route.
Each browser tab's draft key is independent. Reusing a key with changed content is rejected.
Prices use integer hundredths of a dong; round half-up once per contiguous same-rate line.
Displayed quotes are provisional. Changed prices require a new review before confirmation.

No schema migration is applied automatically. Tests create and remove only a randomly named
isolated test database; never point booking test fixtures at the development database.

## Cancellation and live schedule

- POST /booking/cancel requires customer authentication, ownership and CSRF. cancelHold locks
  the booking row, only changes a pending booking to DA_HUY (or HET_HAN if already overdue),
  and never changes inventory counts. Repeats are harmless. Confirmed/completed bookings and
  rows with a successful payment are protected. Payment writers must lock the same booking
  row in their atomic transaction, as specified above; a cancelled booking cannot be confirmed.
- GET /courts/schedule&id=...&date=YYYY-MM-DD&status=1 is public, read-only, no-store.
  It returns server time, slot boundaries and per-court states, without booking IDs, customer
  information or money. It reuses CourtScheduleService, including expiry and elapsed-slot rules.
- Visible schedules poll every 5 seconds after the previous request completes. Only one request
  is active; failures back off to 10/20/30 seconds. Background tabs pause and resync immediately
  on return or reconnection. Aborted responses cannot overwrite a newer snapshot.
- Existing cell state classes are reused. Valid selections remain; blocked selections are removed.
  A monotonic clock anchored to each server response locks started slots every second. Stale
  or failed sync disables progression until availability and the backend quote are refreshed.
  The confirm transaction remains authoritative; polling is not a concurrency lock.
- Expired holds are excluded immediately by server availability rules even before the optional
  expiry job writes HET_HAN. The public state endpoint does not mutate bookings through GET.
- Booking pages use one same-document History API guard for ordinary browser Back; header Back
  uses the same POST cancellation flow and only navigates on a successful server response.
  Refresh, closing a tab, hiding a window and network loss do not cancel holds. Browsers can
  skip synthetic history entries (no user activation, long-press/history jumps, JS disabled);
  in those cases expiry remains the safety net. There are no unload/beacon cancellation writes.

Run `php tests/booking-integration.php --run --browser` for isolated MySQL/HTTP and two Chrome
sessions (Chrome and Node 22 required). Browser expiry uses an overdue fixture in the disposable
test database; it does not wait six minutes or modify the configured application database.
`node tests/schedule-interaction.cjs` checks 09:29:59 → 09:30 with a simulated clock, tab resume,
network failure and out-of-order responses in addition to selection/zoom regressions.
