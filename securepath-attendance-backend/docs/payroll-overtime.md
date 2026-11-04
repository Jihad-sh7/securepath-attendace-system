# Payroll and Department Overtime

## Payroll assumptions

- The dashboard defaults to the current calendar month. The API accepts any non-future calendar month.
- Active employees are included in payroll even if they have no attendance in the selected month; they receive zero hours and zero pay unless required profile data is missing.
- Only completed `present`, `late`, and `early_departure` attendance records are payable.
- Regular minutes are the overlap between actual check-in/check-out and the scheduled work window. Time outside that window is overtime.
- Overtime currently uses a `1.0` multiplier. Regular and overtime amounts are stored separately so a configurable multiplier can be added without changing attendance history.
- The historical department-role rate effective on each attendance date is used. If rates change during a month, the entry can display `Mixed` while its total still uses each correct historical rate.
- Money is calculated as integer cents from four-decimal hourly rates, with half-up rounding per attendance component. Floating-point arithmetic is not used for stored pay.
- Missing roles, departments, schedules, checkouts, rates, or currency mismatches mark an entry `attention`. Unsafe or incomplete records are not silently paid.
- Generated entries snapshot person, department, role, rate, checkout source, and daily attendance breakdown. Recalculating an existing period rebuilds those entries from the latest attendance data and moves the period back to `calculated`.

## Period states

`calculated`, `approved`, and `closed` periods may be recalculated when attendance or rate data changes. A recalculation moves the period back to `calculated`; it must be approved and closed again. State changes and calculations are written to `audit_logs`.

## Automatic checkout

Set `autoCheckoutEnabled` and `maxOvertimeMinutes` on a department. The deadline is:

```text
schedule end timestamp + department maximum overtime minutes
```

The command `php artisan attendance:auto-checkout` finds open attendance records, resolves personal schedules before department schedules, applies weekday overrides, handles overnight end times, and closes only records whose exact deadline has passed. It writes `checkout_source=system_auto`, an explanatory reason, one linked warning alert, reviewer notifications, and a system audit entry.

The operation uses row locks and checks the checkout again inside its transaction, so retries and concurrent runs are idempotent. Missing schedules, disabled rules, and invalid deadlines are skipped rather than guessed.

## Operations

Docker Compose runs `php artisan schedule:work` in the `scheduler` service. For native deployments, supervise both:

```bash
php artisan queue:work --tries=3 --timeout=120
php artisan schedule:work
```

Useful checks:

```bash
php artisan attendance:auto-checkout
php artisan schedule:list
php artisan test
```
