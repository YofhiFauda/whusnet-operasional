# Migration Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix discrepancies in data migration from legacy SQL to the new system, focusing on address synchronization, internet package pricing consistency, and billing/payment accuracy.

**Architecture:** 
1. Update `CustomerController@confirmImport` to synchronize the `address` field directly to the `Customer` model.
2. Ensure `InternetPackage` and `Invoice` models have consistent pricing (0% PPN for legacy data) and total price calculations.
3. Improve `MigrateLegacyDataCommand` to capture more precise payment data if available.

**Tech Stack:** Laravel (PHP 8.x), Eloquent, MySQL.

---

### Task 1: Synchronize Address to Customer Model

**Files:**
- Modify: `app/Http/Controllers/CustomerController.php`

- [ ] **Step 1: Update `confirmImport` for Customers**
  Ensure the `address` field on the `Customer` model is populated with the same value as `CustomerAddress->full_address`.

```php
// In confirmImport, inside Customers processing loop
$customer = Customer::create([
    // ...
    'address' => $this->resolveLegacyAddressText($row), // Add this line
    // ...
]);
```

- [ ] **Step 2: Verify the change**
  Check that `Customer::create` call in `confirmImport` includes the `address` field.

### Task 2: Fix Internet Package Pricing and PPN

**Files:**
- Modify: `app/Http/Controllers/CustomerController.php`

- [ ] **Step 1: Update `confirmImport` for Packages**
  Set `ppn` to 0.00 and `total_price` equal to `monthly_price` for legacy packages.

```php
// In confirmImport, inside Packages processing loop
$package = InternetPackage::create([
    // ...
    'monthly_price' => $row['monthly_price'],
    'ppn' => 0.00,
    'total_price' => $row['monthly_price'],
    // ...
]);
```

- [ ] **Step 2: Verify the change**
  Ensure `InternetPackage::create` call handles `ppn` and `total_price`.

### Task 3: Fix Invoice and Payment Consistency

**Files:**
- Modify: `app/Http/Controllers/CustomerController.php`

- [ ] **Step 1: Update `confirmImport` for Invoices**
  Ensure `ppn` is 0.00 and `subtotal` is correctly mapped for legacy invoices.

```php
// In confirmImport, inside Invoices processing loop
$invoice = Invoice::create([
    // ...
    'subtotal' => $row['monthly_fee'] ?? $service->monthly_price,
    'discount' => 0.00,
    'ppn' => 0.00, // Explicitly 0 for legacy
    'total_amount' => $totalAmount,
    'paid_amount' => 0.00,
    'remaining_amount' => $totalAmount,
    // ...
]);
```

- [ ] **Step 2: Update `confirmImport` for Services (Package Snapshot)**
  Ensure the `CustomerService` snapshot also reflects 0% PPN if it's a legacy service.

```php
// In confirmImport, inside Services processing loop
$ppnPercent = 0.00; // Use 0.00 for legacy instead of 11.00
$totalBill = $monthlyPrice; // No PPN
```

- [ ] **Step 3: Verify consistency**
  Run a test migration or check the logic for discrepancies between `subtotal + ppn` and `total_amount`.

### Task 4: Verify Migration Result

- [ ] **Step 1: Run the migration command**
  Run: `php artisan app:import-legacy-sql`
  Expected: Command completes successfully.

- [ ] **Step 2: Check a migrated customer in the UI**
  Verify that 'Alamat' is no longer 'Belum diisi' in the Detail Pelanggan view.
  Verify that Invoices show correct amounts with 0% PPN.
  Verify that Packages show correct monthly and total prices.

---
