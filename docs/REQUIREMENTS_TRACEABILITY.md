# Requirements Traceability Matrix (RTM)

Sumber requirement: `docs/SRS_Aplikasi_Kantin_Multi_Tenant_ISO29148 v2.pdf` (**SRS v2**, 23 use case) dan
mockup `docs/Mockup UI Kantin Multi-Tenant-v2.dc.html` (layar `#uc-NN`). Rantai v3: mulai Pertemuan 5 setiap
pertemuan menuntaskan minimal satu use case. **Status akhir (Pertemuan 14): 23 dari 23 use case PASS** — lulus test otomatis
dan uji penerimaan browser. Kelengkapan matriks dijaga `tests/Feature/RequirementsTraceabilityTest.php`.

| Use case | Kebutuhan | Pertemuan | Implementasi utama | Test penerimaan | Status |
|---|---|---|---|---|---|
| UC-19 Autentikasi Pengguna | FR-TEN-01 | 5 | `app/Providers/FortifyServiceProvider.php` (authenticateUsing), `app/Support/Auth/LoginLockout.php`, `app/Http/Controllers/DashboardRedirectController.php`, `config/session.php` | `tests/Feature/Auth/LoginLockoutTest.php` + uji browser | PASS |
| UC-21 Kelola Tenant & Skema Komisi | FR-ADM-01 | 5 | `app/Modules/Admin/Services/{CreateTenant,ChangeTenantStatus,ChangeCommissionSchedule}.php`, `app/Modules/Admin/Http/Controllers/*`, view `admin::tenants.*` (verifikasi/tolak/rekening utama ditambahkan P14) | `tests/Feature/TenantOnboardingTest.php`, `AdminManagementTest`, `CommissionScheduleBoundaryTest`, `BankAccountVerificationTest` + uji browser | PASS |
| UC-22 Kelola Meja & QR Code | FR-ADM-02 | 6 | `app/Modules/Admin/Services/{QrTokenService,QrCodeSvg}.php`, `AdminDiningTableController` (qr, status), view `admin::tables.*`, `app/Support/Tokens/OpaqueToken.php` | `tests/Feature/TableQrUseCaseTest.php`, `QrTableSessionTest`, `tests/Unit/OpaqueTokenTest.php` + uji browser | PASS |
| UC-02 Pindai QR Code Meja | FR-CUS-01 | 6 | `app/Modules/Ordering/Services/ResolveTableScan.php`, `ResolveTableQrController`, `CustomerWelcomeController`, `app/Modules/Catalog/Services/TenantOpeningHours.php`, view `ordering::{welcome,canteen-closed,scan-invalid}` | `tests/Feature/CustomerScanUseCaseTest.php`, `QrTableSessionTest` + uji browser | PASS |
| UC-13 Kelola Menu & Stok | FR-TEN-02 | 7 | `app/Modules/Catalog/resources/views/livewire/⚡menu-manager.blade.php`, `app/Modules/Catalog/Services/{MenuPhotoStore,MenuStockService}.php`, migrasi `add_details_to_menus_table` (deskripsi, foto, soft delete); siaran `CatalogChanged` (P14) | `tests/Feature/MenuManagerUseCaseTest.php`, `TenantMenuManagerTest`, `CatalogBroadcastTest` + uji browser | PASS |
| UC-14 Tandai Menu Habis | FR-TEN-03 | 7 | `⚡menu-manager` (sakelar `role=switch`, audit), `⚡menu-catalog` (label HABIS); siaran `CatalogChanged` via Reverb, polling 15 dtk cadangan (P14) | `tests/Feature/MenuManagerUseCaseTest.php`, `CatalogBrowseUseCaseTest`, `CatalogBroadcastTest` + uji browser | PASS |
| UC-01 Telusuri Menu & Tenant | FR-CUS-02 | 7 | `app/Modules/Catalog/Services/PublicCatalogQuery.php` (`browseAll`, `categoryNames`), `⚡menu-catalog`, `TenantOpeningHours`; `CatalogChanged` (siaran WebSocket P14) | `tests/Feature/CatalogBrowseUseCaseTest.php`, `CatalogPublicTest`, `CatalogBroadcastTest` + uji browser | PASS |
| UC-03 Kelola Keranjang Multi-Tenant | FR-CUS-03 | 8 | `app/Modules/Ordering/Services/CartService.php`, `Data/{CartView,CartLine}.php` (tenantGroups, pajak & biaya layanan, grandTotal), komponen `ordering::cart` | `tests/Feature/CartUseCaseTest.php`, `CartServiceTest`, `CartLivewireTest` + uji browser | PASS |
| UC-04 Kustomisasi Item Pesanan | FR-CUS-04 | 8 | komponen `ordering::item-customizer`, `CartService::{modifierGroupsFor,assertSelectionRules,sanitizeNote}`, komponen `catalog::modifier-manager` | `tests/Feature/ItemCustomizationUseCaseTest.php`, `ModifierManagerTest` + uji browser | PASS |
| UC-05 Checkout Pesanan | FR-CUS-06 | 9 | komponen `ordering::checkout` (route `customer.checkout`), `app/Modules/Ordering/Services/CheckoutService.php`, `Data/CheckoutDetails.php`, migrasi `add_checkout_and_pre_order_columns` (`service_mode`, `order_items.note`) | `tests/Feature/CheckoutUseCaseTest.php`, `CheckoutServiceTest`, `OrderCheckoutFlowTest` + uji browser | PASS |
| UC-06 Jadwalkan Pre-Order | FR-CUS-07 | 9 | `app/Modules/Ordering/Services/PreOrderScheduler.php`, `Console/ReleaseScheduledOrders.php` (`ordering:release-scheduled` tiap menit), komponen `ordering::pre-order-settings`; pengingat WhatsApp via UC-12 (P12) | `tests/Feature/PreOrderUseCaseTest.php` + uji browser | PASS |
| UC-07 Bayar via QRIS Dinamis | FR-PAY-01 | 10 | `app/Modules/Payments/Services/{PaymentService,QrisImage}.php` (split, retry 3×, regenerasi, batal), kontrak `PaymentGateway` + `FakeQrisGateway`, komponen `payments::order-payment` (QRIS SVG, hitung mundur, poll 3 dtk), `Ordering/Services/CancelUnpaidOrder.php` | `tests/Feature/QrisPaymentUseCaseTest.php`, `PaymentServiceTest`, `PaymentLivewireTest`, `tests/Unit/FakeQrisGatewayTest.php` + uji browser | PASS |
| UC-08 Verifikasi Pembayaran | FR-PAY-02 | 11 | `app/Modules/Payments/Services/ProcessPaymentWebhook.php` (signature, nominal, lock, expire/deny, proses ulang), `QrisWebhookController` (`POST /webhooks/qris`), `Events/PaymentVerified.php` (after commit), layar `payments::order-payment`; tindak lanjut pengelola `payments::follow-ups` (P14) | `tests/Feature/PaymentVerificationUseCaseTest.php`, `WebhookSettlementTest`, `tests/Unit/WebhookSignatureVerifierTest.php`, `FollowUpUseCaseTest` + uji browser | PASS |
| UC-10 Split Payment Otomatis | FR-PAY-03 | 11 | `app/Modules/Payments/Services/SettlePayment.php` (ledger tenant + `platform_ledger_entries`, `payment_allocations`, selisih 0, pembulatan), `Console/ReprocessFailedSettlements.php` | `tests/Feature/SplitPaymentUseCaseTest.php`, `SettlePaymentTest` + output basis data demo | PASS |
| UC-11 Hitung Estimasi Waktu Tunggu | FR-CUS-05 | 7 | `app/Modules/Kitchen/Services/WaitTimeEstimator.php` (antrean ÷ kapasitas paralel + waktu siap) | `tests/Feature/CatalogBrowseUseCaseTest.php` + uji browser | PASS |
| UC-15 Proses Antrean Dapur (KDS) | FR-TEN-04 | 12 | komponen `kitchen::kitchen-board` (route `tenant.kitchen`), `app/Modules/Kitchen/Services/KitchenService.php` (Terima/Selesai/Diserahkan, batal beralasan → `refund_status`), `Realtime/TenantChannels.php`, `Events/{NewTenantOrderReceived,TenantOrderStatusChanged}.php` (after commit), migrasi `add_kitchen_fields_to_tenant_orders_table`; pengembalian dana `FollowUpService::refundTenantOrder` (P14) | `tests/Feature/KitchenQueueUseCaseTest.php`, `KitchenServiceTest`, `KitchenBoardLivewireTest`, `TenantChannelsTest`, `FollowUpUseCaseTest` + uji browser (Reverb) | PASS |
| UC-09 Lacak Status Pesanan | FR-CUS-08 | 12 | komponen `ordering::order-tracker`, `app/Modules/Ordering/Realtime/{OrderChannels,CustomerChannelUser}.php`, `CustomerBroadcastAuthController` (`customer.broadcast-auth`), `Events/OrderTrackingUpdated.php`, `WaitTimeEstimator` (UC-11) | `tests/Feature/OrderTrackingUseCaseTest.php` + uji browser (Reverb) | PASS |
| UC-12 Kirim Notifikasi Status | FR-PAY-04 | 12 | kontrak `app/Modules/Ordering/Contracts/NotificationGateway.php` + `Gateways/FakeWhatsAppGateway.php` (sandbox), `Services/CustomerNotifier.php` (`notification_deliveries`), `Jobs/SendCustomerNotification.php` (3 percobaan), `Listeners/NotifyCustomerOfOrderEvents.php` | `tests/Feature/OrderNotificationUseCaseTest.php` + log sandbox | PASS |
| UC-16 Lihat Laporan Penjualan | FR-TEN-05 | 13 | komponen `reporting::sales-report` (route `tenant.reports`), `app/Modules/Reporting/Services/TenantSalesReport.php` (omset, transaksi, rata-rata, median, perbandingan periode, menu terlaris, jam sibuk WIB) | `tests/Feature/SalesReportUseCaseTest.php` + uji browser | PASS |
| UC-17 Ekspor Laporan | FR-TEN-06 | 13 | `app/Modules/Reporting/Services/{ReportExportService,SalesReportXlsx,SalesReportPdf}.php` (ZipArchive, dompdf), job `GenerateSalesReportExport`, tabel `report_exports`, route bertanda tangan `tenant.reports.download`, `reporting:prune-exports` | `tests/Feature/ReportExportUseCaseTest.php` + uji browser (unduh .xlsx & PDF) | PASS |
| UC-18 Lihat Rekonsiliasi Bagi Hasil | FR-TEN-07 | 13 | komponen `reporting::reconciliation` (route `tenant.reconciliation`), `app/Modules/Reporting/Services/TenantReconciliation.php` (ledger per pembayaran + referensi, telusur pesanan, perlu peninjauan) | `tests/Feature/ReconciliationUseCaseTest.php` + uji browser | PASS |
| UC-20 Ajukan Penarikan Dana | FR-TEN-08 | 13 | komponen `payments::withdrawal-request` (route `tenant.withdrawals`), `app/Modules/Payments/Services/WithdrawalService.php` (`request`: kunci saldo, minimum, satu pengajuan aktif, ledger `hold`), notifikasi `WithdrawalRequested`; retry deadlock transaksi (P14) | `tests/Feature/WithdrawalRequestUseCaseTest.php`, `WithdrawalServiceTest`, `WithdrawalConcurrencyTest` + uji browser | PASS |
| UC-23 Verifikasi Pencairan Dana | FR-ADM-03 | 13 | komponen `payments::withdrawal-review` (route `admin.withdrawals.index`), `WithdrawalService::{approve,reject}` (bukti transfer privat `admin.withdrawals.proof`, investigasi ledger, idempoten), notifikasi `WithdrawalReviewed`; retry deadlock transaksi (P14) | `tests/Feature/WithdrawalVerificationUseCaseTest.php`, `FinanceFlowTest`, `WithdrawalConcurrencyTest` + uji browser | PASS |

Rencana: P6 UC-22, UC-02 · P7 UC-13, UC-14, UC-01, UC-11 · P8 UC-03, UC-04 · P9 UC-05, UC-06 · P10 UC-07 ·
P11 UC-08, UC-10 · P12 UC-15, UC-09, UC-12 · P13 UC-16, UC-17, UC-18, UC-20, UC-23 · P14 uji penerimaan 23 UC.

## Uji penerimaan browser — Pertemuan 14 (25 September 2026)

Satu skenario berurutan pada basis data demo yang baru di-*seed* (MariaDB 11.4 + Redis), server web, *queue worker*,
dan Reverb berjalan nyata; webhook QRIS dikirim dengan `curl` + HMAC. Tiga peran memakai sesi peramban terpisah
(pengelola, tenant, pelanggan). Hasil: **23 dari 23 PASS**.

| Use case | Hasil | Bukti |
|---|---|---|
| UC-19 Autentikasi Pengguna | PASS | sandi salah ditolak; tenant diarahkan ke /tenant/ayam-pusat/dashboard |
| UC-21 Kelola Tenant & Skema Komisi | PASS | rekening BCA ••••6721 ditambahkan lalu diverifikasi pengelola |
| UC-22 Kelola Meja & QR Code | PASS | QR SVG tampil sekali; URL /q/sZ2XupGP7… |
| UC-02 Pindai QR Code Meja | PASS | halaman sambutan meja; cookie customer_session HttpOnly |
| UC-01 Telusuri Menu & Tenant | PASS | menu per tenant; cari “kopi” → 2 menu |
| UC-11 Hitung Estimasi Waktu Tunggu | PASS | Antrean ± 11–16 mnt |
| UC-04 Kustomisasi Item Pesanan | PASS | grup wajib divalidasi; Regular + Extra shot + catatan tersimpan di keranjang |
| UC-03 Kelola Keranjang Multi-Tenant | PASS | Subtotal (2 tenant) Rp141.000 Pajak Rp14.100 Biaya layanan Rp2.820 Total Rp157.920 |
| UC-05 Checkout Pesanan | PASS | pesanan #ORD-260925-7OFPG8 dibuat; diarahkan ke /kantin/kantin-pusat/order |
| UC-07 Bayar via QRIS Dinamis | PASS | QRIS Rp157.920, sisa waktu 14:57 |
| UC-08 Verifikasi Pembayaran | PASS | signature salah → 401; webhook sah (PAY-ZTIU8STXL4H9XVNO, Rp157920) → 200; halaman “Pembayaran terverifikasi” |
| UC-10 Split Payment Otomatis | PASS | 3 baris alokasi, Σ = Rp157920 (selisih 0) |
| UC-12 Kirim Notifikasi Status | PASS | order:1:paid → whatsapp sent |
| UC-15 Proses Antrean Dapur (KDS) | PASS | Terima → Selesai; koneksi: TERHUBUNG · WEBSOCKET |
| UC-09 Lacak Status Pesanan | PASS | status tenant diperbarui tanpa muat ulang; indikator: REAL-TIME |
| UC-06 Jadwalkan Pre-Order | PASS | slot dipilih (Waktu ambil dipilih 12.30 WIB Mulai dimasak otomatis ± 12.19 Pesanan ditahan ber); tenant_order scheduled 2026-09-25 05:19:00.000000 |
| UC-13 Kelola Menu & Stok | PASS | daftar menu tenant; stok Geprek Keju setelah checkout = 94 |
| UC-14 Tandai Menu Habis | PASS | sakelar tenant → katalog pelanggan HABIS ≤ 6 detik |
| UC-16 Lihat Laporan Penjualan | PASS | laporan menampilkan omset dan menu terlaris |
| UC-17 Ekspor Laporan | PASS | berkas siap: application/pdf 22167 / application/vnd.openxmlformats-officedocument.spreadsheetml.sheet 2030 |
| UC-18 Lihat Rekonsiliasi Bagi Hasil | PASS | transaksi dengan referensi PAY-ZTIU8STXL4H9XVNO |
| UC-20 Ajukan Penarikan Dana | PASS | Rp100.000 diajukan; saldo tertahan = 100000 |
| UC-23 Verifikasi Pencairan Dana | PASS | bukti transfer diunggah → status paid |

Temuan selama uji penerimaan: rekening tenant hasil onboarding (UC-21) berstatus *unverified* tanpa tombol verifikasi,
sehingga prasyarat UC-20 (rekening terverifikasi) tidak dapat dipenuhi lewat antarmuka → ditambahkan tombol
Verifikasi / Tolak / Jadikan utama + `BankAccountVerificationTest`.

Temuan audit per langkah SRS setelah uji penerimaan (Pertemuan 14), seluruhnya sudah ditutup:
UC-14/UC-13/UC-01 kini menyiarkan perubahan katalog lewat WebSocket (`CatalogChanged`); UC-08 alur 3a dan
UC-15 alur 4a memperoleh layar pengelola “Perlu Tindak Lanjut” (`payments::follow-ups`) dengan pengembalian
dana berbasis entri reversal; kebutuhan mutu UC-20/UC-23 dibuktikan dengan dua proses paralel
(`WithdrawalConcurrencyTest`), yang sekaligus menemukan deadlock MariaDB pada pengajuan bersamaan (kini diulang
otomatis).
