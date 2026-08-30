# SQL Templates (Dev Debug Only)

Helper SQL scripts for **local debugging and manual verification** in phpMyAdmin or MySQL CLI.

**Not used by the app.** Change the `SET @...` variables at the top of each file, then run.

| File                                        | Purpose                                                           |
| ------------------------------------------- | ----------------------------------------------------------------- |
| `01_all_members_under_ib.sql`               | Sub-IBs + Direct Clients under a root IB                          |
| `02_direct_clients_under_sub_ib.sql`        | Direct Clients under one Sub-IB                                   |
| `03_profit_loss_direct_client.sql`          | P/L for one Direct Client                                         |
| `04_profit_loss_sub_ib.sql`                 | P/L for one Sub-IB                                                |
| `05_net_deposit_direct_client.sql`          | Net Deposit for one Direct Client (own)                           |
| `06_net_deposit_sub_ib.sql`                 | Net Deposit for one Sub-IB (own userId)                           |
| `07_payment_request_callback_by_user.sql`   | Payment request/callback trace by user                            |
| `08_total_trading_volume_dashboard.sql`     | Total Trading Volume (lots) — IB Dashboard lifetime               |
| `09_total_trading_volume_rebate_report.sql` | Total Trading Volume (lots) — Rebate Report (optional date range) |

Compare results with IB Dashboard and Commission Report when investigating data mismatches.

**Net Deposit** = completed `deposits.amount` − completed `withdrawals.amount` for that member’s `userId` only (no tree rollup).

**Total Trading Volume** = unique `orderId` from `ib_commission_order` where `status = 'completed'`, then `SUM(orders.volume) / 100` lots. Deposit-only rebate rows (`orderId` NULL) do not add volume. Dashboard = lifetime; Rebate Report = optional `@start_date` / `@end_date` on `orderDate` (NULL = all-time).
