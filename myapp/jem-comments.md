# Jem's comments (loans)

Status: total loan, custom months and the net worth fix are built. Currency is still backlog (needs the currency choice).

## Comments (from the screenshot)

1. Add more days/or months on the payment terms on loans
2. Add total amount on the loan
3. Add another currency
4. Monthly total shows the total bill, not the monthly payments I need to pay
5. The total bill shows amount x the number of months of payment, so my total net worth is in extreme negatives

## How loans work today

- A loan is a `recurring_items` row with `kind = debt`. `amount` is the **charge per payment**, and `installments` is the **payments left**.
- Terms are a fixed dropdown: Pay once, 2 payments, 3 payments, 6 months, 12 months (`resources/js/pages/subscriptions/Index.vue`, `terms`).
- Net worth debt = `amount x paymentsLeft()` (`DashboardController::netWorth`). This is what comments 4 and 5 describe.
- Money is shown in PHP only (`resources/js/lib/currency.ts`).

## Plan

### 1. Enter the total loan, not the charge per payment (comments 2, 4, 5)

- The form asks for **Total loan amount**. The per-payment amount is derived: `ceil(total / term)`, with the remainder on the last payment.
- Storage: add `total_amount` (nullable int, centavos) to `recurring_items`. Keep `amount` as the per-payment figure, so the forecast, bills, due lists and `monthlyAmount()` keep working unchanged.
- Net worth debt = **remaining balance** = `total_amount - paid so far`. A loan with no `total_amount` falls back to `amount x paymentsLeft()`.
- Monthly totals already use `amount` (the payment), so comment 4 is covered once the payment is derived from the total. Add a test that a ₱12,000 loan over 12 months adds ₱1,000 to the monthly total, not ₱12,000.
- Show "₱X total, ₱Y per month, Z left" on the loan card.

### 2. Flexible term (comment 1)

- Keep the dropdown and add **Custom** as its last option. Choosing Custom reveals a number input directly below it: "Enter number of months".
- Validation: integer, 1 to 360. It is stored in `installments` and the cadence stays monthly.
- Optional later: a "days" unit (weekly or biweekly cadences already exist).

### 3. Another currency (comment 3)

- Smallest version: a per-user `currency` column (default `PHP`) and `formatPeso` reads it from the shared Inertia props. Display only, no conversion.
- Open question for Jem: which currency (USD, SGD...), and should it be per account or per loan? Per user is the cheap default.

### Order and size

| Step | Work | Size |
|---|---|---|
| 1 | Migration (`total_amount`) + request validation + payment derivation | S |
| 2 | Loan form: total field, Custom months input | S |
| 3 | Net worth uses the remaining balance + tests | S |
| 4 | Currency column + formatter | M |

### Check

One feature test: create a loan with total ₱12,000 and 12 months. Assert the payment is ₱1,000, the monthly total grows by ₱1,000, and net worth debt is ₱12,000, then ₱11,000 after one payment.
