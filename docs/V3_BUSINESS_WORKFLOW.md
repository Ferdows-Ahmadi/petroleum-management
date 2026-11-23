# V3 Business Workflow

## Purpose and evidence boundary

This document defines the proposed business workflow for the next version. It is based on the confirmed office process, a read-only review of the private legacy workbooks, and the current V2.1 application. The private files were not changed or copied. Workbook aliases and all examples below are anonymized.

The central unit of work is a complete commercial deal (`معامله`), not an isolated purchase, sale, warehouse receipt, or payment. A deal connects the commercial promise, physical petroleum movement, money settlement, and final reconciliation while allowing each event to occur on its real date.

## Operating principles

1. Record each real event once and derive ledgers, journal rows, balances, stock, and reports.
2. Keep the common direct supplier-to-customer deal fast.
3. Allow any number of source and destination lines. Do not impose a daily, party, or deal-line limit.
4. Treat storage as an optional source or destination, not a mandatory stop.
5. Keep petroleum delivery and money settlement independent in time.
6. Preserve kilograms as the authoritative quantity and rate per metric ton as the normal price representation.
7. Preserve native currency. Convert only with an explicitly recorded rate.
8. Keep confirmed/finalized events immutable; corrections use explicit reversal or adjustment events.
9. Store private bills as protected evidence and expose them only through authorized application responses.
10. Mark rules that the evidence does not establish as `NEEDS HUMAN CONFIRMATION`.

## End-to-end deal lifecycle

### 1. Customer request

The office records a request from one or more customers:

- customer;
- product;
- approximate/requested kilograms;
- requested delivery date;
- proposed sale rate and currency;
- delivery notes.

This is a planning commitment, not yet an inventory movement, receivable, or revenue event.

### 2. Source arrangement

The office finds one or more sources:

- an external supplier;
- the office's storage;
- a mixture of suppliers and storage.

Each source line records the source party/location, product, expected quantity, purchase rate, currency, and commercial date. A source commitment does not force petroleum into storage.

### 3. Logistics handoff

For a normal direct deal, the source gives petroleum to a carrier and it moves directly to the customer:

`Supplier → truck → Customer`

The truck is transport context, not an asset purchased by the office. A logistics movement may record:

- vehicle and driver snapshots;
- driver contact when operationally required;
- loading and unloading dates/locations;
- loading and unloading weights;
- direct-net or two-stage weighbridge measurement;
- freight, road, driver, weighbridge, and other logistics costs;
- private tickets/bills;
- shortage/excess and explanation.

The workbook evidence shows logistics information mixed with duplicated purchase/sale lines and separate running ledgers. V3 should link logistics to a deal and its lines, but it should not require ordinary users to create a fleet, driver, and trip master record for every delivery.

### 4. Delivery and commercial completion

Actual delivered kilograms come from accepted weighing where applicable:

- `DIRECT_NET`: the recorded net is authoritative;
- `TWO_STAGE`: `net kg = absolute(first kg - second kg)` after both measurements exist.

Sale value is:

`sale amount = delivered kg × sale rate per metric ton ÷ 1,000`

Purchase value uses the same normalized unit rule:

`purchase amount = accepted source kg × purchase rate per metric ton ÷ 1,000`

Legacy blocks sometimes use an equivalent per-kilogram rate without the division by 1,000. Import must normalize the rate basis rather than copy the displayed formula blindly.

### 5. Optional storage movement

If petroleum remains after delivery, the remainder can enter storage. Storage can also receive a deliberate purchase, a quantity returned from a deal, a verified excess, or a supported adjustment. Later, storage can supply a new deal.

Every storage change must be a source-linked movement. Users must never edit a stock total directly.

### 6. Commercial settlement (`تصفیه`)

Settlement is the core reconciliation of a deal. It is not the same as a payment and not the same as truck/loading reconciliation.

The repeated legacy settlement blocks contain a purchase/source side and a sale/destination side. The recurring formulas are:

- line value: `quantity kg × rate per ton ÷ 1,000`;
- total purchase-side value: sum of source and purchase-side cost lines;
- total sale-side value: sum of destination and sale lines;
- preliminary commercial result: `total sale-side value - total purchase-side value`;
- incoming/source quantity: sum of source quantities;
- outgoing/destination quantity: sum of delivered quantities;
- signed quantity difference: a subtraction of the two totals;
- partnership result: an allocated portion of an identified deal result.

Some sheets place freight, shortage, storage, or settlement adjustments on the purchase/cost side. V3 must model those as typed expenses or adjustments and derive the result, rather than encoding them as descriptive purchase rows.

Recommended normalized reconciliation:

`accounted source kg = delivered-to-customers kg + moved-to-storage kg + approved loss kg - approved excess kg`

`unexplained quantity = source kg - accounted source kg`

`gross margin = sale revenue - allocated petroleum cost`

`deal result before partner share = gross margin - deal expenses + approved monetary adjustments`

`office result = deal result before partner share - partner result allocations`

The formula layout is consistent across repeated blocks, but the legacy sign used for the quantity difference is not consistently self-describing. V3 should display words such as “unexplained shortage” and “excess” rather than relying on a bare positive/negative number.

`NEEDS HUMAN CONFIRMATION`: approve the normalized shortage/excess sign convention and the exact rule for allocating common freight and other costs across multiple source/destination lines.

### 7. Later money settlement

Physical completion does not mean that money is settled. A deal can have many receipts and payments on different dates and through different Sarafs.

The recommended office-perspective party sign is:

- positive party balance: the party owes the office;
- negative party balance: the office owes the party.

Accordingly:

- confirmed customer sale increases the customer's positive balance;
- customer payment decreases that balance;
- confirmed supplier purchase increases the office's payable, making the supplier balance more negative;
- payment to the supplier moves that balance toward zero.

A Saraf is a party with a running position, not merely an internal cash/bank account. Under the same office perspective:

- positive Saraf position: the Saraf owes or holds value for the office;
- negative Saraf position: the office owes the Saraf.

When a customer pays 800 units of a 1,000-unit debt through Saraf C:

- the customer receivable falls from 1,000 to 200;
- the office's position with Saraf C increases by 800;
- the deal has an 800 receipt allocation but remains partly unsettled.

When Saraf C pays 600 units to Supplier A on the office's behalf:

- the supplier payable decreases by 600;
- the office's position with Saraf C decreases by 600;
- the payment can be allocated to one or more deals/source lines.

One transfer event must produce all related ledger effects atomically. It must not be entered separately in the customer, supplier, Saraf, and journal screens.

`NEEDS HUMAN CONFIRMATION`: confirm the display wording for positive and negative Saraf positions. The spreadsheets prove a running `previous + one direction - other direction` formula, but legacy direction headings vary between workbook families.

### 8. Final close

A deal can close only when:

- all required source and destination events are confirmed;
- quantity is reconciled or every difference has an approved reason;
- expenses and adjustments are posted;
- partnership allocations are posted where applicable;
- the settlement snapshot is approved;
- unresolved monetary balances are either zero or explicitly left as party receivables/payables.

Closing a commercial deal must not require every party balance to be zero. Later payments remain valid ledger events linked back to the closed deal.

## Common and exceptional workflows

### Normal direct trade

1. Create a deal from Customer B's request.
2. Add Supplier A as one source.
3. Add Customer B as one destination.
4. Record one truck/logistics movement.
5. Finalize weight and delivered quantity.
6. Record typed deal expenses.
7. Review settlement and approve the result.
8. Record later receipts/payments through parties or Sarafs.

No warehouse movement is created when all petroleum moves directly from supplier to customer.

### One source, multiple customers

One source line may feed Customer B and Customer E. Source-to-destination allocation records how many kilograms and how much cost flow to each destination. Each customer receives its own sale/ledger effect; the settlement combines them without duplicating the supplier purchase.

### Multiple sources, one customer

Customer B's need may be satisfied by Supplier A, Supplier F, and storage. Each source retains its own rate, quantity, date, evidence, and payable impact. Allocation joins the sources to the destination so cost and quantity remain traceable.

### Multiple sources and destinations

The model must support a many-to-many matrix. An allocation row connects one source line to one destination line and carries allocated kilograms. Cost attribution may use actual source cost, a weighted pool, or another approved rule.

`NEEDS HUMAN CONFIRMATION`: choose the default cost-attribution method when physical source identity cannot be traced to a specific customer load.

### Leftover to storage

After customer delivery, an allocation sends verified leftover kilograms to a storage destination. That creates a movement into the selected warehouse with source deal and cost provenance. The leftover is not a sale and does not create a customer receivable.

### Later sale from storage

A later deal uses a storage source line. Final delivery creates storage outflow and customer sale effects. Cost should come from a supported inventory-cost stream; V2.1's weighted-average service is a viable starting point.

### Partial and multi-Saraf settlement

A deal may receive and pay money in several installments. Each movement records date, native currency, actual conversion rate when needed, parties, Saraf intermediary, amount, reference/evidence, and allocations. The deal shows commercial result separately from money outstanding.

### Partnership

A partner may participate in a whole deal or only a named source, destination, quantity, or commercial segment. The settlement records:

- participation scope;
- contribution basis;
- result-sharing basis;
- allocated profit or loss;
- linked money/capital events.

The workbooks show capital running balances, settlement/storage/loading result labels, aggregation, and at least one divisor-based split. They do not prove one universal partnership formula.

`NEEDS HUMAN CONFIRMATION`: for each live partnership pattern, confirm whether loss follows profit share, whether capital is returned before result sharing, and whether percentages apply before or after shared expenses.

## Storage claim reconciliation

The storage workbooks repeatedly use two parallel totals:

`remaining storage kg = total quantity entered on purchase/source side - total quantity sold/issued`

`storage claim = total purchase/source-side value - total sale/issue value`

This second value is a pool-level unrecovered-cost/result residual. It is not, by itself, a customer receivable, supplier payable, cash balance, or independently editable inventory value.

Plain-language meaning:

- A positive value means source-side cost has not yet been recovered by storage issues/sales. While stock remains, it is a claim/cost carried against that pool. If stock is zero, a positive residual indicates a gross shortfall or loss in the pool.
- Zero means recorded issue/sale value has exactly recovered recorded source-side value.
- A negative value means issue/sale value exceeds source-side value; its absolute amount is a gross surplus under the sheet's included-cost scope.
- When quantity reaches zero, the residual no longer represents value attached to remaining petroleum. It becomes the pool's gross profit/loss reconciliation result.

Fictional example: 10,000 kg enters a storage pool with 12,000 units of source-side value. If 6,000 kg is issued/sold for 7,800 units, 4,000 kg remains and the claim is 4,200 units. If the final 4,000 kg is later sold for 5,000 units, stock reaches zero and the claim becomes -800 units, indicating an 800-unit gross surplus.

The calculation should remain visible as a reconciliation metric. Financial posting should come from the underlying purchases, sales, expenses, and approved adjustments.

`NEEDS HUMAN CONFIRMATION`: confirm whether every storage workbook includes freight/shortage costs in source-side value before treating the zero-stock residual as final net storage profit/loss.

## Journal authority rule

The daily journal is a derived chronological money view. A payment, receipt, Saraf transfer, deal expense, capital movement, or approved adjustment is authoritative at its source. The journal shows those postings, opening position, movements, closing position, and daily difference without asking users to re-enter them.

A standalone journal event is permitted only for a real money movement that has no more specific business source. It still requires party/account, native currency, direction, effective date, description, and approval.

## Currency rule

- Store original amount and currency for every monetary event.
- Store the explicitly used exchange rate and converted amount only when conversion actually occurs.
- Use a clear rate basis such as `AFN per USD`; never infer it from the amount.
- Do not retroactively revalue historical deal settlement with a later rate.
- Report native-currency totals separately unless a report explicitly uses recorded conversions.
- Rounding and precision remain decimal-safe and server-authoritative.

## State and audit expectations

Suggested deal states are `DRAFT`, `ACTIVE`, `DELIVERED`, `RECONCILING`, `SETTLED`, and `CLOSED`, with `CANCELLED` through a controlled path. Individual source, delivery, weighing, money, and settlement records retain their own lifecycle because they occur at different times.

`NEEDS HUMAN CONFIRMATION`: approve the exact deal state names and which roles may move a deal between them.

## Phase 3 confirmed money workflow (2026-08-30)

- Commercial completion and money settlement remain separate. Completing a Deal creates receivable/payable obligations only.
- `MoneyMovement` is authoritative for every new receipt or payment. Party, Saraf, financial-account, Deal-summary, and Daily Journal rows are projections of that one record.
- A customer receipt reduces the customer's positive receivable and increases the selected Saraf's office position. A supplier payment reduces the supplier's payable and decreases that Saraf position.
- Each movement chooses exactly one route: a Saraf Party or an internal financial account. Saraf is never represented as an `ACC-####` account.
- Installments may use different dates and different Sarafs. A nullable Deal link is an explicit allocation; an unlinked movement remains on the Party account and is not assigned by FIFO.
- Signed Party positions may cross zero, preserving customer credit or supplier prepayment without corrupting history.
- USD is stored directly. AFN records preserve native amount, the explicit AFN-per-USD rate, and the resulting USD amount. No hidden rate is applied.
- Drafts may be corrected or deleted. Confirmation is atomic and locks referenced records; a confirmed movement is immutable. Reallocation and reversal remain deferred.
- Daily Journal is derived from confirmed MoneyMovement, legacy confirmed finance, expenses, and capital events. No journal copy is inserted.

## Phase 4 confirmed commercial settlement workflow (2026-08-31)

- Commercial settlement remains separate from money settlement. A confirmed settlement explains petroleum quantity, source-specific cost, direct Deal expenses, shortage/excess, storage effects, and the final result before partnership; it never fabricates a receipt or payment.
- Each source is explicitly `SUPPLIER` or `STORAGE`; each destination is explicitly `CUSTOMER` or `STORAGE`. Storage is a Warehouse endpoint, never a Supplier Party.
- `DealAllocation` connects a source line to a destination line. Allocated cost is `allocated kg × that source's rate per ton ÷ 1,000`. One-source or one-destination cases allocate automatically; many-to-many cases require explicit user allocation.
- Quantity uses the documented sign rule: `unexplained = source - customer delivery - storage in - shortage + excess`. Confirmation requires zero unexplained quantity and complete source/destination allocations.
- Shortage and excess are explicit draft/confirmed records. Shortage cost uses the selected source's cost rate; it is not valued at the customer sale rate.
- A confirmed storage destination produces exactly one `DEAL_STORAGE_IN` inventory movement with allocation-derived cost. A later `STORAGE` source produces exactly one `DEAL_STORAGE_OUT` movement using the current supported weighted-average cost.
- A Deal expense affects commercial result when confirmed, even if unpaid. Linking it to one confirmed legacy expense records payment/journal provenance without creating a duplicate money row.
- Settlement revisions are snapshots. A confirmed revision is immutable. An authorized correction begins a new draft revision; history is not overwritten.
- Final result before partnership is `customer revenue - source cost allocated to customers - confirmed direct Deal expenses - confirmed shortage cost`. Petroleum moved to storage carries its allocation cost and is not treated as a current-period loss.
- Storage claim is derived as `source-side inflow value - sale/issue value`. At zero stock, a remaining positive/negative value is presented as a storage-pool loss/surplus reconciliation result, not editable inventory value.
