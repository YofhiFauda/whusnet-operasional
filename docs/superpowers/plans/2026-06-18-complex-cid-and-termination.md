# Complex CID and Termination Logic Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement complex CID generation logic (D2X6CRQ...) and backend termination workflow with CID-to-Request ID revert.

**Architecture:** 
1. Refactor `Pop::generateCid` to accept a `Customer` object and use its technical details (OLT, Distribution), village, and name to generate the synthetic CID.
2. Implement a dedicated `terminate` method in `CustomerController` to handle status transitions and ID management.
3. Enhance UI with a real termination flow.

**Tech Stack:** Laravel (PHP), Blade, Tailwind CSS, Alpine.js (for UI modals).

## Global Constraints
- CID format: `{PopPrefix}{OltNumber}{DistributionCode}{RequestID}_{Village}_{Name}`
- Termination: Revert CID display to Request ID (CID field remains in DB but UI/Logic treats as terminated).
- Audit logging for all status changes.
- TDD approach for backend logic.

---

### Task 1: Refactor Pop Model for Complex CID Generation

**Files:**
- Modify: `app/Models/Pop.php`
- Test: `tests/Unit/PopCidGenerationTest.php`

**Interfaces:**
- Consumes: `Customer`, `CustomerTechnicalDetail`, `Village`, `Distribution`
- Produces: `Pop::generateComplexCid(Customer $customer): string`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Pop;
use App\Models\Customer;
use App\Models\Village;
use App\Models\Distribution;
use App\Models\CustomerTechnicalDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PopCidGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_complex_cid_format()
    {
        $pop = Pop::factory()->create(['cid_prefix' => 'D', 'pop_code' => 'SMN']);
        $village = Village::factory()->create(['name' => 'MANGKUJAYAN']);
        $dist = Distribution::factory()->create(['code' => 'X6C', 'pop_id' => $pop->id]);
        
        $customer = Customer::factory()->create([
            'pop_id' => $pop->id,
            'customer_code' => 'RQ001296',
            'full_name' => 'DYAH GALUH',
            'village_id' => $village->id,
            'status' => 'registered'
        ]);

        CustomerTechnicalDetail::create([
            'customer_id' => $customer->id,
            'olt_number' => '2',
            'olt_port' => '1/1/1', // Example existing field
        ]);

        // We need to link Distribution to customer somehow, 
        // usually via ODP or direct technical detail.
        // For this task, we'll assume a way to resolve Distribution.
        
        $cid = $pop->generateComplexCid($customer, $dist);
        $this->assertEquals('D2X6CRQ001296_MANGKUJAYAN_DYAHGALUH', $cid);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/PopCidGenerationTest.php`
Expected: FAIL with "Method generateComplexCid does not exist"

- [ ] **Step 3: Implement complex CID generation in Pop model**

```php
// app/Models/Pop.php

public function generateComplexCid(Customer $customer, ?Distribution $distribution = null): string
{
    $prefix = $this->cid_prefix; // 'D'
    $tech = $customer->customerTechnicalDetail;
    $oltNumber = $tech ? $tech->olt_number : '1'; // Default to 1 if not set
    $distCode = $distribution ? $distribution->code : 'XXX'; // Fallback
    
    $reqId = $customer->customer_code; // 'RQ001296'
    $villageName = strtoupper(str_replace(' ', '', $customer->village?->name ?? 'UNK'));
    $customerName = strtoupper(str_replace(' ', '', $customer->full_name));

    return sprintf('%s%s%s%s_%s_%s', $prefix, $oltNumber, $distCode, $reqId, $villageName, $customerName);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/PopCidGenerationTest.php`
Expected: PASS


---

### Task 2: Implement Termination Logic in CustomerController

**Files:**
- Modify: `app/Http/Controllers/CustomerController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/CustomerTerminationTest.php`

**Interfaces:**
- Consumes: `POST /customers/{customer}/terminate`
- Produces: Status update to `terminated`, Audit Log

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CustomerTerminationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_terminate_customer()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        $customer = Customer::factory()->create(['status' => 'active', 'cid' => 'D2X6C...']);
        
        $response = $this->post("/customers/{$customer->id}/terminate");
        
        $response->assertRedirect();
        $this->assertEquals('terminated', $customer->fresh()->status);
        $this->assertEquals('berhenti', $customer->fresh()->customer_status);
    }
}
```

- [ ] **Step 2: Add route and controller method**

```php
// routes/web.php
Route::post('/customers/{customer}/terminate', [CustomerController::class, 'terminate'])->name('customers.terminate');

// app/Http/Controllers/CustomerController.php
public function terminate(Customer $customer)
{
    \Illuminate\Support\Facades\DB::transaction(function () use ($customer) {
        $oldValues = $customer->toArray();
        
        $customer->update([
            'status' => 'terminated',
            'customer_status' => 'berhenti',
        ]);

        if ($customer->customerService) {
            $customer->customerService->update([
                'service_status' => 'berhenti',
                'billing_status' => 'inactive',
            ]);
        }

        \App\Models\AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'Data Pelanggan',
            'action' => 'terminate',
            'auditable_type' => get_class($customer),
            'auditable_id' => $customer->id,
            'old_values' => $oldValues,
            'new_values' => $customer->fresh()->toArray(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    });

    return redirect()->back()->with('success', "Layanan pelanggan {$customer->full_name} telah diterminasi.");
}
```

- [ ] **Step 3: Run test to verify it passes**

Run: `php artisan test tests/Feature/CustomerTerminationTest.php`
Expected: PASS

---

### Task 3: Update UI for Real Termination Flow

**Files:**
- Modify: `resources/views/customers/index.blade.php`
- Modify: `resources/views/customers/show.blade.php`

- [ ] **Step 1: Update termination trigger in index.blade.php**

```javascript
// resources/views/customers/index.blade.php

function triggerTerminate() {
    if (confirm(`Apakah Anda yakin ingin melakukan TERMINASI / PEMUTUSAN kontrak layanan untuk ${selectedCustomerData.name} (${selectedCustomerData.code})?`)) {
        // Create dynamic form and submit
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/customers/${selectedCustomerData.id}/terminate`;
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = csrfToken;
        
        form.appendChild(csrfInput);
        document.body.appendChild(form);
        form.submit();
    }
    closeActionsModal();
}
```

- [ ] **Step 2: Update display logic for ID Request vs CID in UI**

Ensure the UI displays `customer_code` (Request ID) instead of `cid` when status is `terminated`.

```php
// resources/views/customers/show.blade.php or CustomerController@show

$isCustomer = in_array($status, ['active', 'suspended']);
$displayId = $isCustomer 
    ? $customer->cid 
    : $customer->customer_code;
```



