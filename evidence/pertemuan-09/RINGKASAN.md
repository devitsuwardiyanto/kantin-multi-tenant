# Evidence Pertemuan 09 — Checkout Atomik, Order Induk, Snapshot, Pre-Order
Tanggal: 2026-08-17T05:50:27Z

## Gerbang mutu
```
PHPUnit : 120 passed, 418 assertions (109 -> 120, +11 tes Pertemuan 9; rantai v2)
PHPStan : 0 error (max level)
Pint    : passed
```

## Vertical slice & batas komisi (rantai v2)
- `OrderStatusController` + view `ordering::order` + route `customer.order.show` di modul Ordering
  (`app/Modules/Ordering/routes/customer.php`); pengecualian enkripsi cookie `order_tracking` didaftarkan
  `OrderingServiceProvider::boot()` (`EncryptCookies::except`).
- `CheckoutService::activeCommission()` memakai scope `CommissionScheme::effectiveAt(now())`;
  `test_checkout_at_commission_switch_boundary_never_lacks_a_scheme` (−1 detik → 15%, tepat → 20%) GAGAL bila
  versi lama ditutup `subSecond()`.
- Bukti: `screenshots/m9-checkout-module.png`.
